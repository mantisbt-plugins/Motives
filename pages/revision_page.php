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
 * @package   MantisBT
 * @link      http://www.mantisbt.org
 */
/**
 * MantisBT Core API's
 */
require_once('core.php');

require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_once('motives_api.php');

$f_bug_id = gpc_get_int( 'bug_id', 0 );
$f_bugnote_id = gpc_get_int( 'bugnote_id', 0 );
$f_rev_id = gpc_get_int( 'rev_id', 0 );

$t_title = '';

if( $f_bug_id ) {
	$t_bug_id = $f_bug_id;
	$t_bug_data = bug_get( $t_bug_id, true );
	$t_bug_revisions = array_reverse( motives_revision_list( $t_bug_id ), true );

	$t_title = lang_get( 'issue_id' ) . $t_bug_id;

} else if( $f_bugnote_id ) {
	$t_bug_id = bugnote_get_field( $f_bugnote_id, 'bug_id' );
	$t_bug_data = bug_get( $t_bug_id, true );

	$t_bug_revisions = motives_revision_list( $t_bug_id, $f_bugnote_id );

	$t_title = lang_get( 'bugnote' ) . ' ' . $f_bugnote_id;

} else if( $f_rev_id ) {
	$t_bug_revisions = motives_revision_like( $f_rev_id );

	if( count( $t_bug_revisions ) < 1 ) {
		trigger_error( ERROR_GENERIC, ERROR );
	}

	$t_bug_id = $t_bug_revisions[$f_rev_id]['bug_id'];
	$t_bug_data = bug_get( $t_bug_id, true );

	$t_title = lang_get( 'issue_id' ) . $t_bug_id;

} else {
	trigger_error( ERROR_GENERIC, ERROR );
}

/**
 * Show Bug revision
 *
 * @param array $p_revision Bug Revision Data.
 * @return null
 */
function show_revision( array $p_revision ) {
	static $s_user_access = null;

	$t_label = lang_get( 'bugnote' );

	$t_by_string = sprintf( lang_get( 'revision_by' ), string_display_line( date( config_get( 'normal_date_format' ), $p_revision['timestamp'] ) ), prepare_user_name( $p_revision['reporter_id'] ) );
    $t_rev_value = user_get_name($p_revision['user_id']) . ': ' . motives_format_amount($p_revision['amount']);
?>
<tr class="spacer"><td><a id="revision-<?php echo $p_revision['id'] ?>"></a></td></tr>

<tr>
<th class="category"><?php echo lang_get( 'revision' ) ?></th>
<td colspan="2"><?php echo $t_by_string ?></td>
<tr>
<th class="category"><?php echo $t_label ?></th>
<td colspan="3"><?php echo $t_rev_value ?></td>
</tr>

	<?php
}

layout_page_header( bug_format_summary( $t_bug_id, SUMMARY_CAPTION ) );

layout_page_begin();

?>

<div class="col-md-12 col-xs-12">
<div class="widget-box widget-color-blue2">
<div class="widget-header widget-header-small">
<h4 class="widget-title lighter">
	<i class="ace-icon fa fa-history"></i>
	<?php echo lang_get( 'view_revisions' ), ': ', $t_title ?>
</h4>
</div>

<div class="widget-body">
<div class="widget-toolbox">
	<div class="btn-toolbar">
		<div class="btn-group pull-right">
<?php
if( !$f_bug_id && !$f_bugnote_id ) {
	print_small_button( '?bug_id=' . $t_bug_id, lang_get( 'all_revisions' ) );
}
print_small_button( 'view.php?id=' . $t_bug_id, lang_get( 'back_to_issue' ) );
?>
	</div>
</div>
</div>
<div class="widget-main no-padding">
<div class="table-responsive">
<table class="table table-bordered table-condensed table-striped">
<tr>
<th class="category" width="15%"><?php echo lang_get( 'summary' ) ?></th>
<td colspan="3"><?php echo bug_format_summary( $t_bug_id, SUMMARY_FIELD ) ?></td>
</tr>

<?php foreach( $t_bug_revisions as $t_rev ) {
	show_revision( $t_rev );
} ?>

</table>
</div>
</div>
</div>
</div>
</div>
<?php
layout_page_end();

