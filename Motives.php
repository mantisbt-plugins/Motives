<?php
# MantisBT - a php based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Activity plugin
 * @package    MantisPlugin
 * @subpackage MantisPlugin
 * @link       http://www.mantisbt.org
 */

/**
 * requires MantisPlugin.class.php
 */
require_once(config_get( 'class_path' ) . 'MantisPlugin.class.php');

/**
 * Activity Class
 */
class MotivesPlugin extends MantisPlugin {

	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */
	function register() {
		$this->name        = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		//$this->page        = 'config';

		$this->version  = '1.0';
		$this->requires = array('MantisCore' => '2.0.0',);

		$this->author  = 'Sergey Marchenko';
		$this->contact = 'sergey@mzsl.ru';
		$this->url     = 'http://zetabyte.ru';
	}

	/**
	 * Default plugin configuration.
	 */
	function hooks() {
		$hooks = array('EVENT_MENU_MAIN' => 'menu',
					   'EVENT_VIEW_BUGNOTE' => 'viewNote',
					   'EVENT_VIEW_BUGNOTES_START' => 'viewNoteStart',
					   'EVENT_LAYOUT_RESOURCES' => 'resources',
					   'EVENT_BUGNOTE_ADD_FORM' => 'addNoteForm',
					   'EVENT_BUGNOTE_ADD' => 'addNote',
					   'EVENT_BUGNOTE_EDIT_FORM' => 'editNoteForm',
					   'EVENT_BUGNOTE_EDIT' => 'editNote',
		);

		return $hooks;
	}

	/**
	 * Show any available motives with their associated bugnotes.
	 * @param string $p_event Event name
	 * @param int $p_bug_id Bug ID
	 * @param int $p_bugnote_id Bugnote ID
	 * @param boolean $p_private Private note
	 */
	function viewNote( $p_event, $p_bug_id, $p_bugnote_id, $p_private ) {
		if ( isset( $this->update_cache[ $p_bugnote_id ] ) ) {
			$t_update = $this->update_cache[ $p_bugnote_id ];
			$t_css = $p_private ? 'bugnote-private' : 'bugnote-public';
			$t_css2 = $p_private ? 'bugnote-note-private' : 'bugnote-note-public';

			echo '<tr class="bugnote"><td class="', $t_css, '">', plugin_lang_get( 'bonuses_fines' ),
			'</td><td class="', $t_css2, '">', $t_update['amount'], '</td></tr>';
		}
	}

	/**
	 * Generate and cache a dict of TimecardUpdate objects keyed by bugnote ID.
	 * @param string $p_event Event name
	 * @param int $p_bug_id Bug ID
	 */
	function viewNoteStart( $p_event, $p_bug_id ) {
		$this->update_cache = array();

		if ( !access_has_bug_level( plugin_config_get( 'view_threshold' ), $p_bug_id ) ) {
			return;
		}

		$t_updates = motives_get_by_bug( $p_bug_id );

		foreach( $t_updates as $t_update ) {
			$this->update_cache[ $t_update['bugnote_id']] = $t_update;
		}
	}

	/**
	 * Show appropriate forms for updating time spent.
	 * @param string $p_event Event name
	 * @param int $p_bug_id Bug ID
	 */
	function addNoteForm( $p_event, $p_bug_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}
		echo '<tr ', helper_alternate_class(), '><td class="category">', plugin_lang_get( 'bonuses_fines' ),
		'</td><td><select name="plugin_motives_user"><option value="'. META_FILTER_ANY . '">['.  plugin_lang_get( 'none' ) . ']</option>';

		print_note_option_list(NO_USER);
		echo '</select> ',
		plugin_lang_get( 'amount' ), '<input name="plugin_motives_amount" pattern="^(-)?[0-9]+$" title="', plugin_lang_get('error_numbers') , '" value="0" /></td></tr>';
	}

	/**
	 * Show appropriate forms for updating time spent.
	 * @param string $p_event Event name
	 * @param int $p_bug_id Bug ID
	 * @param int $p_bugnote_id Bugnote ID
	 */
	function editNoteForm( $p_event, $p_bug_id, $p_bugnote_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}
		$t_update = motives_get( $p_bugnote_id );
		if ( $t_update != null ) {
			echo '<tr ', helper_alternate_class(), '><td class="category">', plugin_lang_get( 'bonuses_fines' ),
				'</td><td><select name="plugin_motives_user"><option value="'. META_FILTER_ANY . '">['.  plugin_lang_get( 'none' ) . ']</option>';

			print_note_option_list((int) $t_update['user_id']);
			echo '</select> ',
			plugin_lang_get( 'amount' ), '<input name="plugin_motives_amount" pattern="[0-9]+" title="', plugin_lang_get('error_numbers')
			, '" value="', $t_update['amount'] ,'" /></td></tr>';
		}
	}

	/**
	 * Process form data when bugnotes are added.
	 * @param string $p_event Event name
	 * @param int $p_bug_id Bug ID
	 * @param int $p_bugnote_id Bugnote ID
	 */
	function addNote( $p_event, $p_bug_id, $p_bugnote_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}

		$f_amount = gpc_get_int( 'plugin_motives_amount', 0 );
		$f_user_id = gpc_get_int( 'plugin_motives_user', 0 );
		if ( $f_user_id > 0 ) {
			motives_add($p_bug_id, $p_bugnote_id, auth_get_current_user_id(), $f_user_id, $f_amount);
		}
	}

	/**
	 * Process form data when bugnotes are edited.
	 * @param string $p_event Event name
	 * @param int $p_bug_id Bug ID
	 * @param int $p_bugnote_id Bugnote ID
	 */
	function editNote( $p_event, $p_bug_id, $p_bugnote_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}

		$f_amount = gpc_get_int( 'plugin_motives_amount', 0 );
		$f_user_id = gpc_get_int( 'plugin_motives_user', 0 );

		if ( $f_user_id > 0 ) {
			motives_update($p_bug_id, $p_bugnote_id, auth_get_current_user_id(), $f_user_id, $f_amount);
		}
	}

	/**
	 * Plugin schema.
	 */
	function schema() {
		return array(
			array( 'CreateTableSQL', array( plugin_table( 'motives' ), "
				bug_id			I		NOTNULL UNSIGNED,
				bugnote_id		I		NOTNULL UNSIGNED,
				reporter_id		I		NOTNULL UNSIGNED,
				user_id			I		NOTNULL UNSIGNED,
				timestamp		T		NOTNULL,
				amount			I		NOTNULL
				" ) )
		);
	}

	function menu() {
		$links = array();
		$links[] = array(
			'title' => plugin_lang_get( 'menu' ),
			'url'   => plugin_page( 'motives_page' ),
			'icon'  => 'fa-money'
		);
		return $links;
	}

	function init() {
		$t_path = config_get_global( 'plugin_path' ) . plugin_get_current() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
		set_include_path( get_include_path() . PATH_SEPARATOR . $t_path );
		require_once( 'motives_api.php' );
	}

	function config() {
		return array(
			'view_threshold' => VIEWER,
			'update_threshold' => MANAGER,
			'day_count' => 3,
			'show_avatar' => ON,
			'limit_bug_notes' => 1000
		);
	}

	/**
	 * Create the resource link to load the jQuery library.
	 */
	function resources( $p_event ) {
		return '<link rel="stylesheet" type="text/css" href="' . plugin_file( 'motives.css' ) . '"/>' .
                '<script src="' . plugin_file( 'motives.js' ) . '"></script>';
	}

}