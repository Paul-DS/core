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

try {
	require_once __DIR__ . '/../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect()) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	ajax::init(array('uploadImage'));

	if (init('action') == 'remove') {
		unautorizedInDemo();
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		$object = jeeObject::byId(init('id'));
		if (!is_object($object)) {
			throw new Exception(__('Objet inconnu. Vérifiez l\'ID', __FILE__));
		}
		$object->remove();
		ajax::success();
	}

	if (init('action') == 'byId') {
		$object = jeeObject::byId(init('id'));
		if (!is_object($object)) {
			throw new Exception(__('Objet inconnu. Vérifiez l\'ID ', __FILE__) . init('id'));
		}
		ajax::success(jeedom::toHumanReadable(utils::o2a($object)));
	}

	if (init('action') == 'createSummaryVirtual') {
		jeeObject::createSummaryToVirtual(init('key'));
		ajax::success();
	}

	if (init('action') == 'all') {
		$objects = jeeObject::buildTree(null,init('onlyVisible',true));
		if (init('onlyHasEqLogic') != '') {
			$return = array();
			foreach ($objects as $object) {
				if (count($object->getEqLogic(true, false, init('onlyHasEqLogic'), null, init('searchOnchild', true))) == 0) {
					continue;
				}
				$return[] = $object;
			}
			$objects = $return;
		}
		ajax::success(utils::o2a($objects));
	}

	if (init('action') == 'save') {
		unautorizedInDemo();
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		$object_json = json_decode(init('object'), true);
		if (isset($object_json['id'])) {
			$object = jeeObject::byId($object_json['id']);
		}
		if (!isset($object) || !is_object($object)) {
			$object = new jeeObject();
		}
		utils::a2o($object, jeedom::fromHumanReadable($object_json));
		$object->save();
		ajax::success(utils::o2a($object));
	}

	if (init('action') == 'getChild') {
		$object = jeeObject::byId(init('id'));
		if (!is_object($object)) {
			throw new Exception(__('Objet inconnu. Vérifiez l\'ID', __FILE__));
		}
		$return = utils::o2a($object->getChild());
		ajax::success($return);
	}

	if (init('action') == 'getActionSummary') {
		if (init('object_id') == '') {
			$virtual = eqLogic::byLogicalId('summaryglobal', 'virtual');
		} else {
			$virtual = eqLogic::byLogicalId('summary' . init('object_id'), 'virtual');
		}
		if (!is_object($virtual)) {
			throw new Exception(__('L\'objet n\'existe pas : ', __FILE__) . init('object_id'));
		}
		$removeCmd = array();
		foreach ($virtual->getCmd() as $cmd) {
			if ($cmd->getType() == 'action' && $cmd->getConfiguration('summary::key') == init('summary')) {
				continue;
			}
			$removeCmd[] = $cmd->getId();
		}
		ajax::success(array(
			'html' => $virtual->toHtml(init('version')),
			'removeCmd' => $removeCmd
		));
	}

	if (init('action') == 'getEqLogicsFromSummary') {
		$object = jeeObject::byId(init('id'));
		if (!is_object($object)) {
			throw new Exception(__('Objet inconnu. Vérifiez l\'ID ', __FILE__) . init('id'));
		}
		$return = $object->getEqLogicsFromSummary(init('summary'), init('onlyEnable'), init('onlyVisible'));
		ajax::success($return);
	}

	function jeeAjax_objectToHtml($_id=-1, $_version='dashboard', $_category='all', $_tag='all', $_summary='') {
		$html = array();
		if ($_summary == '') {
			$eqLogics = eqLogic::byObjectId($_id, true, true);
		} else {
			$object = jeeObject::byId($_id);
			$eqLogics = $object->getEqLogicBySummary(init('summary'), true, false);
		}
		if (count($eqLogics) > 0) {
			foreach ($eqLogics as $eqLogic) {
				if ($_category != 'all' && $eqLogic->getCategory(init('category')) != 1) {
					continue;
				}
				if ($_tag != 'all' && strpos($eqLogic->getTags(), init('tag')) === false) {
					continue;
				}
				$order = $eqLogic->getOrder();
				while(isset($html[$order])){
					$order++;
				}
				$html[$order] = $eqLogic->toHtml($_version);
			}
		}
		if ($_summary == '') {
			$scenarios = scenario::byObjectId($_id,false,true);
			if(count($scenarios) > 0){
				foreach ($scenarios as $scenario) {
					$order = $scenario->getOrder();
					while(isset($html[$order])){
						$order++;
					}
					$html[$order] = $scenario->toHtml($_version);
				}
			}
		}
		ksort($html);
		return implode($html);
	}
	if (init('action') == 'toHtml') {
		if (init('id') == '' || init('id') == 'all' || is_json(init('id'))) {
			if (is_json(init('id'))) {
				$objects = json_decode(init('id'), true);
			} else {
				$objects = array();
				if(init('summary') == ''){
					foreach (jeeObject::buildTree(null, true) as $object) {
						if ($object->getConfiguration('hideOnDashboard', 0) == 1) {
							continue;
						}
						$objects[] = $object->getId();
					}
				} else {
					foreach((jeeObject::all()) as $object) {
						$objects[] = $object->getId();
					}
				}
			}
			$return = array();
			$i = 0;
			foreach ($objects as $id) {
				$html = jeeAjax_objectToHtml($id, init('version'), init('category', 'all'), init('tag', 'all'), init('summary'));
				$return[$i . '::' . $id] = $html;
				$i++;
			}
			ajax::success($return);
		} else {
			$html = jeeAjax_objectToHtml(init('id'), init('version'), init('category', 'all'), init('tag', 'all'), init('summary'));
			ajax::success($html);
		}
	}

	if (init('action') == 'setOrder') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		$position = 1;
		foreach (json_decode(init('objects'), true) as $id) {
			$object = jeeObject::byId($id);
			if (is_object($object)) {
				$object->setPosition($position);
				$object->save();
				$position++;
			}
		}
		ajax::success();
	}

	if (init('action') == 'getUISelectList') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		ajax::success(jeeObject::getUISelectList(init('none'), true));
	}

	if (init('action') == 'getSummaryHtml') {
		if (init('ids') != '') {
			$return = array();
			foreach (json_decode(init('ids'), true) as $id => $value) {
				if ($id == 'global') {
					$return['global'] = array(
						'html' => jeeObject::getGlobalHtmlSummary($value['version']),
						'id' => 'global',
					);
					continue;
				}
				$object = jeeObject::byId($id);
				if (!is_object($object)) {
					continue;
				}
				$return[$object->getId()] = array(
					'html' => $object->getHtmlSummary($value['version']),
					'id' => $object->getId(),
				);
			}
			ajax::success($return);
		} else {
			$object = jeeObject::byId(init('id'));
			if (!is_object($object)) {
				throw new Exception(__('Objet inconnu. Vérifiez l\'ID', __FILE__));
			}
			$info_object = array();
			$info_object['id'] = $object->getId();
			$info_object['html'] = $object->getHtmlSummary(init('version'));
			ajax::success($info_object);
		}
	}

	if (init('action') == 'removeImage') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		unautorizedInDemo();
		$object = jeeObject::byId(init('id'));
		if (!is_object($object)) {
			throw new Exception(__('Vue inconnu. Vérifiez l\'ID ', __FILE__) . init('id'));
		}
		$object->setImage('data', '');
		$object->setImage('sha512', '');
		$object->save();
		@rrmdir(__DIR__ . '/../../core/img/object');

		$files = ls(__DIR__ . '/../../data/object/','object'.$object->getId().'-*');
		if (count($files)  > 0) {
			foreach ($files as $file) {
				unlink(__DIR__ . '/../../data/object/'.$file);
			}
		}
		ajax::success();
	}

	if (init('action') == 'uploadImage') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		unautorizedInDemo();
		$object = jeeObject::byId(init('id'));
		if (!is_object($object)) {
			throw new Exception(__('Objet inconnu. Vérifiez l\'ID', __FILE__));
		}
		if (init('file') == '') {
			if (!isset($_FILES['file'])) {
				throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
			}
			$extension = strtolower(strrchr($_FILES['file']['name'], '.'));
			if (!in_array($extension, array('.jpg', '.png'))) {
				throw new Exception(__('Extension du fichier non valide (autorisé .jpg .png) : ', __FILE__) . $extension);
			}
			if (filesize($_FILES['file']['tmp_name']) > 5000000) {
				throw new Exception(__('Le fichier est trop gros (maximum 5Mo)', __FILE__));
			}
			$upfilepath = $_FILES['file']['tmp_name'];
		} else {
			$extension = strtolower(strrchr(init('file'), '.'));
			$upfilepath = init('file');
		}
		$files = ls(__DIR__ . '/../../data/object/','object'.$object->getId().'-*');

		if (count($files)  > 0) {
			foreach ($files as $file) {
				unlink(__DIR__ . '/../../data/object/'.$file);
			}
		}
		$object->setImage('type', str_replace('.', '', $extension));
		$object->setImage('sha512', sha512(file_get_contents($upfilepath)));
		$filename = 'object'.$object->getId().'-'.$object->getImage('sha512') . '.' . $object->getImage('type');
		$filepath = __DIR__ . '/../../data/object/' . $filename;
		file_put_contents($filepath,file_get_contents($upfilepath));
		if (!file_exists($filepath)) {
			throw new \Exception(__('Impossible de sauvegarder l\'image',__FILE__));
		}
		$object->save();
		ajax::success(array('filepath' => $filepath));
	}

	throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}