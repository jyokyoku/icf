<?php
class ICF_Dispatcher {
	protected $_action_key = 'action';

	protected $_actions = array();

	public function __construct() {
		add_action( 'admin_init', array( $this, 'dispatch_action' ) );
	}

	public function dispatch_action() {
		if ( !$action = icf_get_array( $_GET, $this->_action_key ) ) {
			return;
		}

		if ( isset( $this->_actions[$action] ) ) {
			ksort( $this->_actions[$action] );

			foreach ( $this->_actions[$action] as $function ) {
				call_user_func( $function );
			}
		}
	}

	public function add_action( $key, $function, $priority = 10 ) {
		if ( is_callable( $function ) ) {
			$action_id = $this->_build_action_unique_id( $key, $function, $priority );
			$this->_actions[$key][$priority][$action_id] = $function;
		}
	}

	public function remove_action( $key, $function, $priority = 10 ) {
		$action_id = $this->_build_action_unique_id( $key, $function, $priority );

		if ( isset( $this->_actions[$key][$priority][$action_id] ) ) {
			unset( $this->_actions[$key][$priority][$action_id] );
		}
	}

	protected function _build_action_unique_id( $key, $function, $priority ) {
		static $action_id_count = 0;

		if ( is_string( $function ) ) {
			return $function;
		}

		if ( is_object( $function ) ) {
			$function = array( $function, '' );

		} else {
			$function = (array)$function;
		}

		if ( is_object( $function[0] ) ) {
			if ( function_exists( 'spl_object_hash' ) ) {
				return spl_object_hash( $function[0] ) . $function[1];

			} else {
				$obj_idx = get_class( $function[0] ) . $function[1];

				if ( !isset( $function[0]->ipf_action_id ) ) {
					if ( false === $priority ) {
						return false;
					}

					$obj_idx .= isset( $this->_actions[$key][$priority] ) ? count( (array)$this->_actions[$key][$priority] ) : $action_id_count;
					$function[0]->ipf_action_id = $action_id_count;

					++$action_id_count;

				} else {
					$obj_idx .= $function[0]->ipf_action_id;
				}

				return $obj_idx;
			}

		} else if ( is_string( $function[0] ) ) {
			return $function[0] . $function[1];
		}
	}
}