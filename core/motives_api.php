<?php

function motives_add( $p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount ) {
	$t_update_table = plugin_table( 'motives', 'Motives' );
	$t_query        = "INSERT INTO $t_update_table (
					bug_id,
					bugnote_id,
					reporter_id,
					user_id,
					timestamp,
					amount
				) VALUES (
					" . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ',
					' . db_param() . ' )';
	db_query( $t_query, array(
		$p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, db_now(), $p_amount
	) );
}

function motives_update( $p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount ) {
	$t_update_table = plugin_table( 'motives', 'Motives' );
	$t_query        = "DELETE FROM $t_update_table WHERE bugnote_id =" . db_param();
	db_query( $t_query, array( $p_bugnote_id ) );
	motives_add( $p_bug_id, $p_bugnote_id, $p_reporter_id, $p_user_id, $p_amount );
}

function motives_get( $p_bugnote_id ) {
	$t_update_table = plugin_table( 'motives', 'Motives' );
	$t_query        = "SELECT * FROM $t_update_table WHERE bugnote_id=" . db_param();
	$t_result       = db_query( $t_query, array( $p_bugnote_id ) );

	if ( db_num_rows( $t_result ) < 1 ) {
		return null;
	}

	if ( $t_row = db_fetch_array( $t_result ) ) {
		return $t_row;
	} else {
		return null;
	}
}

function motives_get_by_bug( $p_bug_id ) {
	$t_update_table = plugin_table( 'motives', 'Motives' );
	$t_query        = "SELECT * FROM $t_update_table WHERE bug_id=" . db_param();
	$t_result       = db_query( $t_query, array( $p_bug_id ) );

	if ( db_num_rows( $t_result ) < 1 ) {
		return null;
	}
	$t_rows = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_rows[] = $t_row;
	}
	return $t_rows;
}

/**
 * Get latest bug notes for period
 * @param int    $p_project_id Project id
 * @param string $p_date_from  Start date
 * @param string $p_date_to    End date
 * @param int    $p_user_id    Filter only this user bug notes
 * @param int    $p_limit      Bug notes limit
 * @return array
 */
function motives_get_latest_bugnotes( $p_project_id, $p_date_from, $p_date_to, $p_user_id = null, $p_bonus_user_id, $p_category_id, $p_limit = 500 ) {
	$c_from          = strtotime( $p_date_from );
	$c_to            = strtotime( $p_date_to ) + SECONDS_PER_DAY - 1;
	$c_user_id       = empty( $p_user_id ) ? 0 : intval( $p_user_id, 10 );
	$c_bonus_user_id = empty( $p_bonus_user_id ) ? 0 : intval( $p_bonus_user_id, 10 );
	$c_category_id   = empty( $p_category_id ) || $p_category_id == -1 ? 0 : intval( $p_category_id, 10 );

	if( $c_to === false || $c_from === false ) {
		error_parameters( array($p_date_from, $p_date_to) );
		trigger_error( ERROR_GENERIC, ERROR );
	}
	$t_bug_table          = db_get_table( 'mantis_bug_table' );
	$t_bugnote_table      = db_get_table( 'mantis_bugnote_table' );
	$t_bugnote_text_table = db_get_table( 'mantis_bugnote_text_table' );
	$t_update_table       = plugin_table( 'motives', 'Motives' );

	$t_query    = "SELECT b.*, t.note, m.amount, m.user_id as bonus_user_id
                    FROM      $t_bugnote_table b
                    LEFT JOIN $t_bug_table bt ON b.bug_id = bt.id
                    LEFT JOIN $t_bugnote_text_table t ON b.bugnote_text_id = t.id
                    LEFT JOIN $t_update_table m ON m.bugnote_id = t.id  
                    WHERE 	bt.project_id=" . db_param() . " AND
                    		b.date_submitted >= $c_from AND b.date_submitted <= $c_to AND
                    		LENGTH(t.note) > 0
                    " .
		(!empty($c_user_id) ? ' AND b.reporter_id = ' . $c_user_id : '') .
		(!empty($c_bonus_user_id) ? ' AND m.user_id = ' . $c_bonus_user_id : '') .
		(!empty($c_category_id) ? ' AND bt.category_id = ' . $c_category_id : '') .
		' ORDER BY b.id DESC LIMIT ' . $p_limit;

	$t_bugnotes = array();

	$t_result = db_query_bound( $t_query, array($p_project_id) );

	while( $row = db_fetch_array( $t_result ) ) {
		$t_bugnotes[]               = $row;
	}
	return $t_bugnotes;
}

/**
 * Group bugnotes by bug id
 * @param array $p_bugnotes Bug notes
 * @return array
 */
function motives_group_by_bug ( $p_bugnotes ) {
	$t_group_by_bug = array();
	foreach ( $p_bugnotes as $t_bugnote ) {
		$bug_id = (int) $t_bugnote['bug_id'];
		if( empty($t_group_by_bug[$bug_id]) ) $t_group_by_bug[$bug_id] = array();
		$t_group_by_bug[$bug_id][] = $t_bugnote;
	}
	return $t_group_by_bug;
}