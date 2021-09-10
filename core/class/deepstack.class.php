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
		$_url = trim(trim($this->getConfiguration('url')),'/') . $_url;
		log::add('deepstack', 'debug', 'URL ' . $_url);
		log::add('deepstack', 'debug', 'Image ' . $_image);
		if ($_reference == '') {
			$data['image'] = curl_file_create($_image);
		} else {
			$data['image1'] = curl_file_create($_reference);
			$data['image2'] = curl_file_create($_image);
			log::add('deepstack', 'debug', 'Image reference ' . $_reference);
		}
		$curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $_url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array( 'Content-Type: multipart/form-data' ));
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $return = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$last_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
		if ($httpCode != '200') {
			log::add('deepstack', 'debug', 'Error ' . $httpCode . ' ' . $last_url);
		} else {
			log::add('deepstack', 'debug', 'Result ' . $return);
		}
		/*if ($_reference == '') {
			$data = 'image=@' . $_image;
		} else {
			$data = "image1=@" . $_reference . "' -F image2='@" . $_image;
		}
		$cmd = "curl -L -X POST " . $_url . " -H 'Content-Type: multipart/form-data' -F '" . $data . "'";
		$result = exec($cmd);
		log::add('deepstack', 'debug', 'Cmd ' . $cmd);
		log::add('deepstack', 'debug', 'Result ' . $result);*/
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
		$this->checkAndUpdateCmd('getObjectDetection:predictions', json_encode($data['predictions']));
	}

	public function getFaceMatch($_files) {
		$data =$this->callOpenData('/v1/vision/face/match', $_files[0], $_files[1]);
		$this->checkAndUpdateCmd('getFaceMatch:similarity', $data['similarity']);
		$this->checkAndUpdateCmd('getFaceMatch:success', $data['success']);
	}

	public function getFaceDetection($_files) {
		$data =$this->callOpenData('/v1/vision/face', $_files[0]);
		$this->checkAndUpdateCmd('getFaceDetection:success', $data['success']);
		$this->checkAndUpdateCmd('getFaceDetection:predictions', json_encode($data['predictions']));
		if (array_key_exists(['confidence'],$data['predictions'][0])) {
			$this->checkAndUpdateCmd('getFaceDetection:predictions:0:confidence', $data['predictions'][0]['confidence']);
		} else {
			$this->checkAndUpdateCmd('getFaceDetection:predictions:0:confidence', 0);
		}
	}

	public function getFaceRecognition($_files) {
		$data =$this->callOpenData('/v1/vision/face/recognize', $_files[0]);
		$this->checkAndUpdateCmd('getFaceRecognition:success', $data['success']);
		$this->checkAndUpdateCmd('getFaceRecognition:predictions', json_encode($data['predictions']));
	}

}

class deepstackCmd extends cmd {
	public function execute($_options = null) {
		$eqLogic = $this->getEqLogic();
		if (array_key_exists('files', $_options)) {
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
