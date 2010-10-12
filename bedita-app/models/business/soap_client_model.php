<?php
/*-----8<--------------------------------------------------------------------
 * 
 * BEdita - a semantic content management framework
 * 
 * Copyright 2010 ChannelWeb Srl, Chialab Srl
 * 
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the Affero GNU General Public License as published 
 * by the Free Software Foundation, either version 3 of the License, or 
 * (at your option) any later version.
 * BEdita is distributed WITHOUT ANY WARRANTY; without even the implied 
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the Affero GNU General Public License for more details.
 * You should have received a copy of the Affero GNU General Public License 
 * version 3 along with BEdita (see LICENSE.AGPL).
 * If not, see <http://gnu.org/licenses/agpl-3.0.html>.
 * 
 *------------------------------------------------------------------->8-----
 */

/**
 * SOAP client model
 * 
 * Minima SOAP client, supporting both php soap module and nusoap library
 *
 * @version			$Revision$
 * @modifiedby 		$LastChangedBy$
 * @lastmodified	$LastChangedDate$
 * 
 * $Id$
 */
class SoapClientModel extends BEAppModel 
{
	var $useTable = false;
	protected $client = null;
	protected $soapParams = array();
	protected $connected = false;
	
	public function setup($soapCfg = "default", array& $options = array()) {
		
		$params = Configure::read("soap." . $soapCfg);
		if(!empty($options)) {
			$params = array_merge($params, $options);
		}
		// do nothing if client exixts and params are identical
		$diff = array_diff_assoc($this->soapParams, $params);
		if($this->client == null || !empty($diff) ) {
			$this->soapParams = $params;
			$this->initClient();
		}
	}
	
	private function useNuSoap() {
		return !empty($this->soapParams['useLib']) && $this->soapParams['useLib'] == "nusoap";
	}
	
	public function clientReady() {
		return ($this->client != null && !empty($this->soapParams));
	}
	
	private function initClient() {
		if($this->useNuSoap()) {
			App::import ('Vendor', 'nusoap', array ('file' => 'nusoap' . DS . 'nusoap.php') );
			$this->client = new nusoap_client($this->soapParams["wsdl"], "wsdl");
			if(!empty($this->soapParams["debugLevel"])) {
				$this->client->setDebugLevel($this->soapParams["debugLevel"]);				
			}
		} else {
			
			$options = array_diff_key($this->soapParams, array("useLib" =>"", "wsdl"=> "", "debugLevel"=> ""));
			$this->client = new SoapClient($this->soapParams["wsdl"], $options);
		}
	}
	
	public function debugMsg() {
		$res = null;
		if($this->useNuSoap()) {
			$res = $this->client->getDebug();
		} else {
			$res = $this->client->__getLastRequestHeaders();
			$res .= $this->client->__getLastRequest();
			$res .= $this->client->__getLastResponseHeaders();
			$res .= $this->client->__getLastResponse();
		}		
		return $res;
	}
	
	
	public function call($method, array $params) {
		if(!$this->clientReady()) {
			$this->setup();
		}
		$res = null;
		if($this->useNuSoap()) {
			$res = $this->client->call($method, $params);
		} else {
			$res = $this->client->$method($params);
		}
		return $res;		
	}
	
}
?>