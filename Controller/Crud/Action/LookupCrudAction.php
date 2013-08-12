<?php

App::uses('CrudAction', 'Crud.Controller/Crud');

/**
 * LookupCrudAction
 *
 * Used for getting the results for autocomplete form inputs
 *
 */
class LookupCrudAction extends CrudAction {

/**
 * _settings
 *
 * 'findMethod' the find method to use when looking for results
 *
 * 'findParams' the find params to use when looking for lookup results
 *
 * 'term' is the term to search for, if it's not defined explicitly it wil be read from the request
 *
 * 'fields' defines the fields used in queries, and the format of the response. the three default
 * fields have the following meaning:
 * 	id    : Defaults to primary key. For use if there's a hidden field - this is that value
 *  value : Defaults to display field. The field put into the text input when a selection is made
 *
 * The value key must be defined for this classes logic, the id and value keys must be defined for
 * jquery ui to work - unless a customized js handler is defined. It's possible to reduce/add keys
 * by modifying the action config.
 *
 * 'strategy' a field-indexed array of strategy names to use when looking for values. There are 3
 * default strategies, beginsWith, endsWith and contains. To implement a different strategy an
 * event listener needs to be defined listening for a Crud.Lookup.$field event, which can then
 * modify the findParams settings (or model state) as desired. The Crud.Lookup.$field event is fired
 * also for default strategies.
 *
 * 'defaultStrategy' if there is no field-specific strategy, this is the strategy to use.
 *
 * @var array
 */
	protected $_settings = array(
		'enabled' => true,
		'findMethod' => 'all',
		'findParams' => array(),
		'term' => null,
		'fields' => array(
			'id' => null,
			'value' => null
		),
		'strategy' => array(),
		'defaultStrategy' => 'beginsWith',
		'viewVar' => 'data'
	);

/**
 * Constant representing the scope of this action
 *
 * @var integer
 */
	const ACTION_SCOPE = CrudAction::SCOPE_MODEL;

/**
 * implementedEvents
 *
 * @return void
 */
	public function implementedEvents() {
		return array(
			'Crud.afterLookup' => array('callable' => 'afterLookup'),
		);
	}

/**
 * afterLookup
 *
 * Format results in standard jquery.ui autocomlete format, even though the json
 * will be wrapped in the standard ApiListener format and so still need the source
 * method to be overwritten to refer to data.data instead of data (in a js file).
 *
 * @param CakeEvent $event
 */
	public function afterLookup(CakeEvent $event) {
		$fields = $this->config('fields');
		$keys = array_keys($fields);
		$fields = array_values($fields);

		$alias = $this->_model->alias;

		foreach ($event->subject->items as &$item) {
			$_item = [];

			foreach ($keys as $i => $key) {
				$_item[$key] = $item[$alias][$fields[$i]];
			}

			$item = $_item;
		}
	}

/**
 * HTTP GET handler
 *
 * Process the lookup using pagination
 *
 * Triggers the following callbacks
 *	- Crud.beforeLookup
 *	- Crud.afterLookup
 *
 * @return void
 */
	protected function _get() {
		$params = $this->_findParams();

		$items = $this->Components->load('Paginator', $params)->paginate($this->_model);
		$subject = $this->_crud->trigger('afterLookup', compact('items'));
		$items = $subject->items;

		if ($items instanceof Iterator) {
			$items = iterator_to_array($items);
		}

		$this->_controller->set(array('success' => true, $this->viewVar() => $items));
		$this->_crud->trigger('beforeRender');
	}

/**
 * _findParams
 *
 * Manipulate the find parameters to add only the fields for the lookup result.
 * Called by the inherited _handle function.
 *
 * After the action config has been defined, fire off a beforeLookup event
 * to permit further modifications to the pagination settings.
 */
	protected function _findParams() {
		$config = $this->_initConfig();

		$term = $config['term'];

		$value = $config['fields']['value'];

		$findParams = $config['findParams'];

		$findParams['findMethod'] = $config['findMethod'];
		$findParams['fields'] = array_values(array_unique($config['fields']));
		$findParams['fields'][0] = 'DISTINCT ' . $this->_model->escapeField($findParams['fields'][0]);

		if (empty($findParams['order'])) {
			$findParams['order'] = [$value => 'asc'];
		}

		$findParams = $this->_addCondition($value, $term, $findParams);

		$subject = $this->_crud->trigger('beforeLookup', compact('findMethod', 'findParams'));
		$findParams = $subject->findParams;

		return $findParams;
	}

/**
 * _addCondition
 *
 * Add a condition for the given field. There are only simple strategies to choose from.
 * To implement any other functionality implement a Crud.Lookup.<fieldname> event listener
 * to modify the findParams property on the subject as appropriate.
 *
 * @param string $field
 * @param string $term
 * @param array $findParams
 * @return array
 */
	protected function _addCondition($field, $term, $findParams) {
		if ($term === '') {
			$findParams['conditions'][$field . ' !='] = '';
			$findParams['conditions']['NOT'][$field] = null;
		} else {
			$strategy = $this->config('strategy.' . $field) ?: $this->config('defaultStrategy');

			if ($strategy === 'beginsWith') {
				$findParams['conditions'][$field . ' LIKE'] = "$term%";
			} elseif ($strategy === 'endsWith') {
				$findParams['conditions'][$field . ' LIKE'] = "%$term";
			} elseif ($strategy === 'contains') {
				$findParams['conditions'][$field . ' LIKE'] = "%$term%";
			}
		}

		$subject = $this->_crud->trigger('Crud.LookupCondition', compact('findParams', 'strategy', 'field'));
		$findParams = $subject->findParams;

		return $findParams;
	}

/**
 * _initConfig
 *
 * Initialize the term and fields from the request object if they haven't
 * been explicity set
 *
 * @return array modified config
 */
	protected function _initConfig() {
		$config = $this->config();

		if (!isset($config['term']) && isset($this->_request->query['term'])) {
			$config['term'] = $this->_request->query['term'];
		}

		foreach (array_keys($config['fields']) as $field) {
			if (isset($config['fields'][$field])) {
				continue;
			}
			if (isset($this->_request->query[$field . '_field'])) {
				$config['fields'][$field] = $this->_request->query[$field . '_field'];
			} else {
				if ($field === 'id') {
					$config['fields'][$field] = $this->_model->primaryKey;
				} else {
					$config['fields'][$field] = $this->_model->displayField;
				}
			}
		}

		$this->config($config);

		return $config;
	}

}
