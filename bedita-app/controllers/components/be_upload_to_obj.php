<?php
/*-----8<--------------------------------------------------------------------
 * 
 * BEdita - a semantic content management framework
 * 
 * Copyright 2008 ChannelWeb Srl, Chialab Srl
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
 * 
 * @link			http://www.bedita.com
 * @version			$Revision$
 * @modifiedby 		$LastChangedBy$
 * @lastmodified	$LastChangedDate$
 * 
 * $Id$
 */
class BeUploadToObjComponent extends Object {
	var $components	= array('BeFileHandler', 'BeBlipTv') ;

 	/**
	 * Contructor function
	 * @param Object &$controller pointer to calling controller
	 */
	function startup(&$controller) {
		//keep tabs on mr. controller's params
		$this->params = $controller->params;
		$this->BeFileHandler->startup($controller) ;
	}

	/**
	 * Uploads a file to location and create stream object.
	 
	 * @return object_id if upload was successful, false otherwise.
	 */
	function upload($dataStream=null) {
		$result = false ;
		if (empty($this->params["form"]["Filedata"]["name"]))
			throw new BEditaException(__("No file in the form", true));

		if ($this->params['form']['Filedata']['error'])
			throw new BEditaUploadPHPException($this->params['form']['Filedata']['error']);
			
		// Prepare data
		if (!empty($dataStream)) {
			$data = array_merge($dataStream, $this->params['form']['Filedata']);
		} else {
			$data = $this->params['form']['Filedata'];
		}
		$data['mime_type'] = $this->BeFileHandler->getMimeType($data);
		unset($data['type']);
		
		if (!empty($this->params['form']['mediatype'])) {
			$data['mediatype'] = $this->params['form']['mediatype'];
		}
		
		$override = (isset($this->params['form']['override'])) ? ((boolean)$this->params['form']['override']) : false ;

		if (empty($data['title']))
			$data['title'] = $data['name'];

		$data['path']	= $data['tmp_name'] ;

		if (empty($data["status"]))
			$data["status"] = "on";

		unset($data['tmp_name']) ;
		unset($data['error']) ;

		$result = $this->BeFileHandler->save($data) ;
		
		return $result;
	}
	
	/**
	 * Create obj stream from URL.
	 * Form must to have: url, title, lang.
	 * @return boolean true if upload was successful, int $id otherwise.
	 */
	function uploadFromURL($dataURL, $clone=false) {

		$result = false ;
		$getInfoURL = false;
		
		$url = $this->recognizeMediaProvider($dataURL['url'], $provider, $name);
		
		// Prepare data
		switch($provider) {
			case 'youtube': {
				$dataURL['title']		= (!empty($dataURL['title'])) ? trim($dataURL['title']) : 'youtube video';
				$dataURL['name']		= preg_replace("/[\'\"]/", "", $dataURL['title']) ;
				$dataURL['mime_type']	= "video/$provider" ;
				$dataURL['path']		= $url ;
				$dataURL['provider']	= $provider ;
				$dataURL['uid']  	 	= $name ;
				$dataURL['thumbnail']	= $this->getThumbnail($dataURL);
			} break ;
			case 'blip': {
				if(!($this->BeBlipTv->getInfoVideo($name) )) {
					throw new BEditaMediaProviderException(__("Multimedia  not found",true)) ;
				}
				
				if(@empty($dataURL['title']))
					$dataURL['title'] = $this->BeBlipTv->info['title'] ;
				else
					$dataURL['title'] = trim($dataURL['title']) ;
								
				$dataURL['name']		= preg_replace("/[\'\"]/", "", $dataURL['title']) ;
				$dataURL['mime_type']	= "video/$provider" ;
				$dataURL['path']		= $this->BeBlipTv->info['url'] ;
				$dataURL['provider']	= $provider ;
				$dataURL['uid']  	 	= $name ;
				$dataURL['thumbnail']	= $this->BeBlipTv->info['thumbnailUrl'];
			} break ;
			default:
				$dataURL['path'] = $dataURL["url"];
				$getInfoURL = true;
				break;
			
		}

		if (empty($dataURL["status"]))
			$dataURL['status'] = "on";
		
		if (!empty($this->params['form']['mediatype'])) {
			$dataURL['mediatype'] = $this->params['form']['mediatype'];
		}
		
		$id = $this->BeFileHandler->save($dataURL, $clone, $getInfoURL) ;
		
		return $id;
		
	}
	
	function cloneMediaObject($data) {
		if (!empty($data["id"]))
			unset($data["id"]);
			
		if(preg_match(Configure::read("validate_resorce.URL"), $data["path"])) {
			$data['url'] = $data["path"];
			return $this->uploadFromURL($data, true);
		} else {
			$data['path'] = Configure::read("mediaRoot") . $data["path"];
			if (empty($data["size"])) {
				$data["size"] = filesize($data["path"]);
			}
			if (!empty($this->params['form']['mediatype'])) {
				$data['mediatype'] = $this->params['form']['mediatype'];
			}
			return $this->BeFileHandler->save($data, true);
		}
	}
	
	
	function getThumbnail($data) {
		if (empty($data["provider"]) || empty($data["uid"])) {
			$url = (!empty($data['url']))? $data['url'] : $data['path'];
			$this->recognizeMediaProvider($url, $provider, $uid);
		} else {
			$provider = $data["provider"];
			$uid = $data["uid"];
		}
		
		$thumbnail = null;
		
		if ($provider == "youtube") {
			$this->conf = Configure::getInstance() ;
			$thumbnail = sprintf($this->conf->provider_params["youtube"]["urlthumb"], $uid);
		} elseif ($provider == "blip") {
			if (empty($this->BeBlipTv->info)) {
				if(!($this->BeBlipTv->getInfoVideo($uid) )) {
					throw new BEditaMediaProviderException(__("Multimedia  not found",true)) ;
				}
			}
			$thumbnail	= $this->BeBlipTv->info['thumbnailUrl'];
		}
		return $thumbnail;
	}
	
	/**
	 * recognize provider from url
	 */
	private function recognizeMediaProvider($url, &$provider, &$uid) {
		$conf 		= Configure::getInstance() ;
		
		foreach($conf->media_providers as $provider => $expressions) {
			foreach($expressions as $expression) {
				if(preg_match($expression, $url, $matched)) {
					$uid = $matched[1] ;
					
					return $matched[0] ;
				}	
			}
		}
		$provider = "";
		return false ;
	}
}
?>