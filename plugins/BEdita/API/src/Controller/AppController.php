<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2016 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\API\Controller;

use BEdita\API\Error\ExceptionRenderer;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Network\Exception\ForbiddenException;
use Cake\Network\Exception\NotAcceptableException;
use Cake\Routing\DispatcherFactory;
use Cake\Routing\Router;

/**
 * Base class for all API Controller endpoints.
 *
 * @since 4.0.0
 *
 * @property \BEdita\API\Controller\Component\JsonApiComponent $JsonApi
 */
class AppController extends Controller
{

    /**
     * {@inheritDoc}
     */
    public $paginate = [
        'maxLimit' => 100,
        'order' => [
            'id' => 'asc',
        ],
    ];

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        parent::initialize();

        if (!$this->apiKeyCheck()) {
            throw new ForbiddenException('No valid API KEY found');
        }

        $this->response = $this->response->withHeader('X-BEdita-Version', Configure::read('BEdita.version'));

        $this->loadComponent('BEdita/API.Paginator', (array)Configure::read('Pagination'));

        $this->loadComponent('RequestHandler');
        if ($this->request->is(['json', 'jsonapi'])) {
            $this->loadComponent('BEdita/API.JsonApi', [
                'contentType' => $this->request->is('json') ? 'json' : null,
                'checkMediaType' => $this->request->is('jsonapi'),
            ]);

            $this->RequestHandler->setConfig('inputTypeMap.json', [[$this->JsonApi, 'parseInput']], false);
            $this->RequestHandler->setConfig('viewClassMap.json', 'BEdita/API.JsonApi');
        }

        $this->loadComponent('Auth', [
            'authenticate' => ['BEdita/API.Jwt', 'BEdita/API.Anonymous'],
            'authorize' => [
                'BEdita/API.Endpoint' => [
                    'disallowAnonymousApplications' => Configure::read('Security.disallowAnonymousApplications'),
                ],
            ],
            'loginAction' => ['_name' => 'api:login'],
            'loginRedirect' => ['_name' => 'api:login'],
            'unauthorizedRedirect' => false,
            'storage' => 'Memory',
        ]);

        if (empty(Router::fullBaseUrl())) {
            Router::fullBaseUrl(
                rtrim(
                    sprintf('%s://%s/%s', $this->request->scheme(), $this->request->host(), $this->request->getAttribute('base')),
                    '/'
                )
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function beforeFilter(Event $event)
    {
        if ((Configure::read('debug') || Configure::read('Accept.html')) && $this->request->is('html')) {
            return $this->html();
        } elseif (!$this->request->is(['json', 'jsonapi'])) {
            throw new NotAcceptableException('Bad request content type "' . implode('" "', $this->request->accepts()) . '"');
        }

        return null;
    }

    /**
     * Check API KEY from request header.
     * API KEYS are stored in configuration with this structure:
     *
     *  'ApiKeys' => [
     *    'sdgwr89081023jfdklewRASdasdwdfswdr' => [
     *      'label' => 'web app', // (optional)
     *      'origin' => 'example.com', // (optional) could be '*'
     *    ],
     *    'w4nvwpq5028DDfwnrK2933293423nfnaa4' => [
     *       ....
     *    ],
     *
     * Check rules are:
     *   - if no Api Keys are defined -> request is always accepted
     *   - if one or more Api Keys are defined
     *      - current X-Api-Key header value should be one of these keys
     *      - if corresponding Key has an 'origin' request origin should match
     *      - otherwise an error response is sent - HTTP 403
     *
     * @return bool True if check is passed, false otherwise
     */
    protected function apiKeyCheck()
    {
        $apiKeys = Configure::read('ApiKeys');
        if (!empty($apiKeys)) {
            $requestKey = $this->request->getHeaderLine('X-Api-Key');
            if (!$requestKey || !isset($apiKeys[$requestKey])) {
                return false;
            }
            $key = $apiKeys[$requestKey];
            if (!empty($key['origin']) && $key['origin'] !== '*' &&
                $key['origin'] !== $this->request->getHeaderLine('Origin')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Action to display HTML layout.
     *
     * @return \Cake\Http\Response
     * @throws \Cake\Network\Exception\NotFoundException
     */
    protected function html()
    {
        $this->request->allowMethod('get');
        $method = $this->request->getMethod();
        $url = $this->request->getRequestTarget();

        $viewBuilder = $this->viewBuilder();

        // render JSON API response
        try {
            $this->request->env('HTTP_ACCEPT', 'application/json');
            $this->loadComponent('BEdita/API.JsonApi');

            $viewBuilder->setClassName('BEdita/API.JsonApi');
            $this->invokeAction();
            $responseBody = (string)$this->render()->getBody();

            $this->dispatchEvent('Controller.shutdown');
            $dispatcher = DispatcherFactory::create();
            $args = [
                'request' => $this->request,
                'response' => $this->response,
            ];
            $dispatcher->dispatchEvent('Dispatcher.afterDispatch', $args);

            $this->components()->unload('JsonApi');
            unset($this->JsonApi);
            $viewBuilder->setTemplate('Common/html');
        } catch (\Exception $exception) {
            $renderer = new ExceptionRenderer($exception);
            $response = $renderer->render();
            $responseBody = (string)$response->getBody();
            $this->response = $this->response->withStatus($response->getStatusCode());
            $viewBuilder->setTemplate('Error/error');
        }

        $this->set(compact('method', 'responseBody', 'url'));

        // render HTML
        $viewBuilder->setClassName('View');
        $this->response->type('html');

        return $this->render();
    }
}
