<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class deepstack extends eqLogic {
	public function loadCmdFromConf($type) {
		if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
			return;
		}
		$content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
		if (!is_json($content)) {
			return;
		}
		$device = json_decode($content, true);
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
				if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
				|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
					$cmd = $liste_cmd;
					break;
				}
			}
			if ($cmd == null || !is_object($cmd)) {
				$cmd = new deepstackCmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd, $command);
				$cmd->save();
			}
		}
	}

	public function postSave() {
		$this->loadCmdFromConf('deepstack');
	}

	public function callOpenData($_url, $_image, $_reference = '') {
		$_url = $this->getConfiguration('url') . $_url;
		log::add('deepstack', 'debug', 'Parse ' . $_url);
		if ($_reference == '') {
			$data['image'] = new CURLFile(realpath($_image));
		} else {
			$data['image1'] = new CURLFile(realpath($_reference));
			$data['image2'] = new CURLFile(realpath($_image));
		}
		$request_http = new com_http($_url);
    $request_http->setNoReportError(true);
		$request_http->setPost($data);
    $return = $request_http->exec(60,1);
		log::add('deepstack', 'debug', 'Result ' . $return);
		return json_decode($return, true);
	}

	public function getSceneRecognition($_files) {
		$data =$this->callOpenData('/v1/vision/scene', $_files[0]);
		$this->checkAndUpdateCmd('getSceneRecognition:success', $data['success']);
		$this->checkAndUpdateCmd('getSceneRecognition:confidence', $data['confidence']);
		$this->checkAndUpdateCmd('getSceneRecognition:label', $data['label']);
	}

	public function getObjectDetection($_files) {
		$data =$this->callOpenData('/v1/vision/detection', $_files[0]);
		$this->checkAndUpdateCmd('getObjectDetection:success', $data['success']);
		$this->checkAndUpdateCmd('getObjectDetection:predictions', $data['predictions']);
	}

	public function getFaceMatch($_files) {
		$data =$this->callOpenData('/v1/vision/face/match', $_files[0], $_files[1]);
		$this->checkAndUpdateCmd('getFaceMatch:similarity', $data['similarity']);
		$this->checkAndUpdateCmd('getFaceMatch:success', $data['success']);
	}

	public function getFaceDetection($_files) {
		$data =$this->callOpenData('/v1/vision/face', $_files[0]);
		$this->checkAndUpdateCmd('getFaceDetection:success', $data['success']);
		$this->checkAndUpdateCmd('getFaceDetection:predictions', $data['predictions']);
	}

	public function getFaceRecognition($_files) {
		$data =$this->callOpenData('/v1/vision/face/recognize', $_files[0]);
		$this->checkAndUpdateCmd('getFaceRecognition:success', $data['success']);
		$this->checkAndUpdateCmd('getFaceRecognition:predictions', $data['predictions']);
	}

}

class deepstackCmd extends cmd {
	public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		if ($_options['title'] == '') {
			$images = $_options['files'];
		} else {
			$images = explode(',',$_options['title']);
		}
		switch ($this->getLogicalId()) {
			case 'getFaceRecognition':
				$eqLogic->getFaceRecognition($images);
				break;
			case 'getFaceDetection':
				$eqLogic->getFaceDetection($images);
				break;
			case 'getFaceMatch':
				$eqLogic->getFaceMatch($images);
				break;
			case 'getObjectDetection':
				$eqLogic->getObjectDetection($images);
				break;
			case 'getSceneRecognition':
				$eqLogic->getSceneRecognition($images);
				break;
		}
	}
}
?>
