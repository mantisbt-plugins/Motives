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
 * Motives plugin
 * @package    MantisPlugin
 * @subpackage MantisPlugin
 * @link       http://www.mantisbt.org
 */

/**
 * requires MantisPlugin.class.php
 */
require_once(config_get( 'class_path' ) . 'MantisPlugin.class.php');

/**
 * Motives Class
 */
class MotivesPlugin extends MantisPlugin
{
	const BASE_NAME = 'Motives';

	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */
	function register() {
		$this->name        = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page        = 'config';

		$this->version  = '1.1';
		$this->requires = array( 'MantisCore' => '1.3.0', );

		$this->author  = 'Sergey Marchenko';
		$this->contact = 'sergey@mzsl.ru';
		$this->url     = 'http://zetabyte.ru';
	}

	/**
	 * Default plugin configuration.
	 */
	function hooks() {
		$hooks = array( 'EVENT_MENU_MAIN'           => 'menu',
						'EVENT_VIEW_BUGNOTE'        => 'view_note',
						'EVENT_VIEW_BUGNOTES_START' => 'view_note_start',
						'EVENT_LAYOUT_RESOURCES'    => 'resources',
						'EVENT_BUGNOTE_ADD_FORM'    => 'add_note_form',
						'EVENT_BUGNOTE_ADD'         => 'add_note',
						'EVENT_BUGNOTE_EDIT_FORM'   => 'edit_note_form',
						'EVENT_BUGNOTE_EDIT'        => 'edit_note',
						'EVENT_BUGNOTE_DELETED'     => 'delete_note'
		);

		return $hooks;
	}

	/**
	 * Show any available motives with their associated bugnotes.
	 * @param string  $p_event      Event name
	 * @param int     $p_bug_id     Bug ID
	 * @param int     $p_bugnote_id Bugnote ID
	 * @param boolean $p_private    Private note
	 */
	function view_note( $p_event, $p_bug_id, $p_bugnote_id, $p_private ) {
		if ( !access_has_bug_level( plugin_config_get( 'view_threshold' ), $p_bug_id ) ) {
			return;
		}

		if ( isset( $this->update_cache[$p_bugnote_id] ) ) {
			$t_update    = $this->update_cache[$p_bugnote_id];
			$t_css       = $p_private ? 'bugnote-private' : 'bugnote-public';
			$t_css2      = $p_private ? 'bugnote-note-private' : 'bugnote-note-public';
			$t_revisions = '';
			if ( isset( $this->revision_cache[$p_bugnote_id] )
					&& ((int) $this->revision_cache[$p_bugnote_id] > 1)) {
				$t_revisions = '<a href="' . plugin_page( 'revision_page' ) . '&bugnote_id=' . $p_bugnote_id . '">' .
					sprintf( lang_get( 'view_num_revisions' ), $this->revision_cache[$p_bugnote_id] ) . '</a>';
			}

			echo '<tr class="bugnote"><td class="', $t_css, '">',
			plugin_lang_get( 'bonuses_fines' ),
			"<br/>",
			$t_revisions,
			'</td><td class="', $t_css2, '">',
				user_get_name($t_update['user_id']) . ': ' . motives_format_amount($t_update['amount']),
			'</td></tr>';
		}
	}

	/**
	 * Generate and cache a dict of TimecardUpdate objects keyed by bugnote ID.
	 * @param string $p_event  Event name
	 * @param int    $p_bug_id Bug ID
	 */
	function view_note_start( $p_event, $p_bug_id ) {
		$this->update_cache = array();
		$this->revision_cache = array();

		if ( !access_has_bug_level( plugin_config_get( 'view_threshold' ), $p_bug_id ) ) {
			return;
		}

		$t_updates = motives_get_by_bug( $p_bug_id );

		foreach ( $t_updates as $t_update ) {
			$this->update_cache[$t_update['bugnote_id']] = $t_update;
			$this->revision_cache[$t_update['bugnote_id']] = motives_revision_count($p_bug_id, $t_update['bugnote_id']);
		}
	}

	/**
	 * Show appropriate forms for updating time spent.
	 * @param string $p_event  Event name
	 * @param int    $p_bug_id Bug ID
	 */
	function add_note_form( $p_event, $p_bug_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}
		echo '<tr ', helper_alternate_class(), '><td class="category">', plugin_lang_get( 'bonuses_fines' ),
			'</td><td colspan="5"><select name="plugin_motives_user"><option value="' . META_FILTER_ANY . '">[' . plugin_lang_get( 'none' ) . ']</option>';

		print_note_option_list( NO_USER );
		echo '</select> ',
		plugin_lang_get( 'amount' ), '<input name="plugin_motives_amount" pattern="^(-)?[0-9]+$" title="', plugin_lang_get( 'error_numbers' ), '" value="0" /></td></tr>';
	}

	/**
	 * Show appropriate forms for updating time spent.
	 * @param string $p_event      Event name
	 * @param int    $p_bug_id     Bug ID
	 * @param int    $p_bugnote_id Bugnote ID
	 */
	function edit_note_form( $p_event, $p_bug_id, $p_bugnote_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}
		$t_update = motives_get( $p_bugnote_id );
		$t_user_id = $t_update != null ? (int) $t_update['user_id'] : NO_USER;
		$t_amount = $t_update != null ?  (int) $t_update['amount'] : 0;

		echo '<tr ', helper_alternate_class(), '><td class="category">', plugin_lang_get( 'bonuses_fines' ),
			'</td><td><select name="plugin_motives_user"><option value="' . META_FILTER_ANY . '">[' . plugin_lang_get( 'none' ) . ']</option>';

		print_note_option_list( $t_user_id );

		echo '</select> ',
		plugin_lang_get( 'amount' ), '<input name="plugin_motives_amount" pattern="^(-)?[0-9]+$" title="', plugin_lang_get( 'error_numbers' )
		, '" value="', $t_amount, '" /></td></tr>';
	}

	/**
	 * Process form data when bugnotes are added.
	 * @param string $p_event      Event name
	 * @param int    $p_bug_id     Bug ID
	 * @param int    $p_bugnote_id Bugnote ID
	 */
	function add_note( $p_event, $p_bug_id, $p_bugnote_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}

		$f_amount  = gpc_get_int( 'plugin_motives_amount', 0 );
		$f_user_id = gpc_get_int( 'plugin_motives_user', 0 );
		if ( $f_user_id > 0 && $f_amount != 0) {
			$t_reporter_id = auth_get_current_user_id();
			motives_add( $p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount );
			motives_revision_add( $p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount );
		}
	}

	/**
	 * Process form data when bugnotes are edited.
	 * @param string $p_event      Event name
	 * @param int    $p_bug_id     Bug ID
	 * @param int    $p_bugnote_id Bugnote ID
	 */
	function edit_note( $p_event, $p_bug_id, $p_bugnote_id ) {
		if ( !access_has_bug_level( plugin_config_get( 'update_threshold' ), $p_bug_id ) ) {
			return;
		}

		$f_amount  = gpc_get_int( 'plugin_motives_amount', 0 );
		$f_user_id = gpc_get_int( 'plugin_motives_user', 0 );

		if ( $f_user_id > 0 ) {
			$t_reporter_id = auth_get_current_user_id();
			$t_old = motives_get( $p_bug_id );
			motives_update( $p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount );
			motives_revision_add( $p_bug_id, $p_bugnote_id, $t_reporter_id, $f_user_id, $f_amount );
			$t_old_value = '';
			$t_new_value = user_get_name( $f_user_id ) . ': ' . motives_format_amount($f_amount);
			if ( !empty( $t_old ) ) {
				$t_old_value = user_get_name($t_old['user_id']) . ': ' . motives_format_amount($t_old['amount']);
			}
			plugin_history_log($p_bug_id, 'bonus_edited', $t_old_value, $t_new_value, null, self::BASE_NAME );
		}
	}

	/**
	 * Delete a bonuses
	 * @param $p_event      Event name
	 * @param $p_bug_id     Bug id
	 * @param $p_bugnote_id Bug note id
	 */
	function delete_note( $p_event, $p_bug_id, $p_bugnote_id ) {
		motives_delete( $p_bugnote_id );
	}

	/**
	 * Plugin schema.
	 */
	function schema() {
		return array(
			array( 'CreateTableSQL', array( plugin_table( 'bonus' ), "
				bug_id			I		NOTNULL UNSIGNED,
				bugnote_id		I		NOTNULL UNSIGNED,
				reporter_id		I		NOTNULL UNSIGNED,
				user_id			I		NOTNULL UNSIGNED,
				timestamp		T		NOTNULL,
				amount			I		NOTNULL
				" ) ),
			array( 'CreateTableSQL', array( plugin_table( 'bonus_revision' ), "
				id              I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				bug_id			I		NOTNULL UNSIGNED,
				bugnote_id		I		NOTNULL UNSIGNED,
				reporter_id		I		NOTNULL UNSIGNED,
				user_id			I		NOTNULL UNSIGNED,
				timestamp		I		UNSIGNED,
				amount			I		NOTNULL
				" ) )

		);
	}

	function menu() {
		if ( !access_has_global_level( plugin_config_get( 'view_report_threshold' ) ) ) {
			return array();
		}
		return array( '<a href="' . plugin_page( 'motives_page' ) . '">' . plugin_lang_get( 'menu' ) . '</a>', );
	}

	function init() {
		$t_path = config_get_global( 'plugin_path' ) . plugin_get_current() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
		set_include_path( get_include_path() . PATH_SEPARATOR . $t_path );
		require_once('motives_api.php');
	}

	function config() {
		return array(
			'view_threshold'        => VIEWER,
			'update_threshold'      => MANAGER,
			'view_report_threshold' => MANAGER,
			'day_count'             => 3,
			'show_avatar'           => ON,
			'limit_bug_notes'       => 100000
		);
	}

	/**
	 * Create the resource link
	 */
	function resources( $p_event ) {
		return '<link rel="stylesheet" type="text/css" href="' . plugin_file( 'motives.css' ) . '"/>' .
			'<script src="' . plugin_file( 'motives.js' ) . '"></script>';
	}

}