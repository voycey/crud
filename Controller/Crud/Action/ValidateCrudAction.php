<?php

App::uses('Hash', 'Utility');
App::uses('CrudAction', 'Crud.Controller/Crud');
App::uses('CrudValidationException', 'Crud.Error/Exception');

/**
 * Handles 'Validate' Crud actions
 *
 * An api-only method for validating whether data is valid
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Christian Winther, 2013
 */
class ValidateCrudAction extends CrudAction {

/**
 * Default settings for 'validate' actions
 *
 * `enabled` Is this crud action enabled or disabled
 *
 * `validateId` ID Argument validation - by default it will inspect your model's primary key
 * and based on it's data type either use integer or uuid validation.
 * Can be disabled by setting it to "false". Supports "integer" and "uuid" configuration
 * By default it's configuration is NULL, which means "auto detect"
 *
 * `validateOptions` Raw array passed as 2nd argument to saveAll()
 * If you configure a key with your action name, it will override the default settings.
 *
 * @var array
 */
	protected $_settings = array(
		'enabled' => true,
		'findMethod' => 'first',
		'view' => null,
		'validateId' => null,
		'validateOptions' => array(
		),
		'messages' => array(
			'success' => array(
				'text' => 'Successfully validated {name}'
			),
			'error' => array(
				'text' => 'Could not validate {name}'
			)
		),
		'serialize' => array()
	);

/**
 * Generic validate action
 *
 * Triggers the following callbacks
 *	- Crud.initialize
 *	- Crud.beforeValidate
 *	- Crud.afterValidate
 *	- Crud.beforeFind
 *	- Crud.recordNotFound
 *	- Crud.afterFind
 *	- Crud.beforeRender
 *
 * @param string $id
 * @return void
 * @throws NotFoundException If record not found
 */
	protected function _handle($id = null) {
		if (!$this->_request()->is('api')) {
			return false;
		}

		if ($id && !$this->_validateId($id)) {
			return false;
		}

		$request = $this->_request();
		$model = $this->_model();

		if ($request->is('post')) {
			$this->_trigger('beforeValidate', compact('id'));
			if ($model->saveAll($request->data, array('validate' => 'only') + $this->config('validateOptions'))) {
				$subject = $this->_trigger('afterValidate', array('id' => $id, 'success' => true));
			} else {
				$errors = $this->_crud()->validationErrors();
				throw new CrudValidationException($errors);
			}
		}

		$this->_trigger('beforeRender');
	}

}
