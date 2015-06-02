<?php
/**
 * Mantis Bug Table History - A script to aggregate MantisBT issue 
 * histories onto a single page for simple management reporting 
 * across all projects. It's a bit specific to GGP Systems
 * Limited at present. The MantisBT team are explicity given licence to 
 * use this source code, with or without modification, within the 
 * MantisBT project, without being required to the retain the BSD 
 * 3-clause licence that follows, by directly replacing it with the GPL 
 * licence that the MantisBT project is maintained under.
 * 
 * @package MantisBT
 * @author Murray Crane <murray.crane@ggpsystems.co.uk>
 * @copyright (c) 2015, GGP Systems Limited
 * @license BSD 3-clause license (see LICENSE)
 * @version 1.3
* 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of GGP Systems Limited nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL GGP SYSTEMS LIMITED BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

$status_ref_arr = array(
	10 => 'New',
	20 => 'More Information Required',
	25 => 'Reopened',
	50 => 'Assigned',
	60 => 'Developing',
	80 => 'Resolved',
	83 => 'Testing',
	85 => 'Merge',
	87 => 'Deferred',
	90 => 'Closed',
	999 => 'Reopened or Closed',
);

$tester_ref_arr = array(
    'Customer' => 'Customer',
	'alanb' => 'Alan Bloor',
	'callumb' => 'Callum Bryson',
    'daniel' => 'Daniel Chong',
    'davidj' => 'David James',
	'dmitryk' => 'Dmitry Kuznetsov',
	'keithp' => 'Keith Prosser',
    'louisg' => 'Louis Genasi',
    'murrayc' => 'Murray Crane',
    'roger' => 'Roger Gill-Carey',
    'ruzinab' => 'Ruzina Begum',
	'simonm' => 'Simon Mason',
    'tim maxwell' => 'Tim Maxwell',
);

$mbht_name = 'mantis_bug_history_table';
$mbt_name = 'mantis_bug_table';
$mcfst_name = 'mantis_custom_field_string_table';
$mpt_name = 'mantis_project_table';
$mpht_name = 'mantis_project_hierarchy_table';
$mpult_name = 'mantis_project_user_list_table';
$mut_name = 'mantis_user_table';

$dsn = array(
	'username' => "mantisbt",
	'password' => "W7S4C5yecpb6ug9m",
	'hostspec' => "mysql.ggp.local",
	'database' => "bugtracker"
		);

$version_string = " v1.3";

if( date( 'w' ) == 1 ) {
	$yesterday_start = strtotime( 'last friday midnight' );
	$yesterday_end = strtotime( 'last friday 23:59:59' );
} else {
	$yesterday_start = strtotime( 'yesterday' );
	$yesterday_end = strtotime( 'yesterday 23:59:59' );
}
$week_start = strtotime( '7 days ago midnight' );
$month_start = strtotime( '30 days ago midnight');
$today = time();

$start_timestamp = $yesterday_start;
$end_timestamp = $yesterday_end;
$period_string = "Yesterday";

$t_duration = filter_input( INPUT_POST, 'duration', FILTER_VALIDATE_INT, array( 'options' => array( 'default' => 1, 'min_range' => 1, 'max_range' => 4 )));
$t_start_date = filter_input( INPUT_POST, 'start_date', FILTER_VALIDATE_REGEXP, array( 'options' => array( 'default' => 0, 'regexp' => '/^([1-9]|[12][0-9]|3[01])-([1-9]|1[012])-(19|20)\d\d$/' )));
$t_end_date = filter_input( INPUT_POST, 'end_date', FILTER_VALIDATE_REGEXP, array( 'options' => array( 'default' => 0, 'regexp' => '/^([1-9]|[12][0-9]|3[01])-([1-9]|1[012])-(19|20)\d\d$/' )));
$t_new_project = filter_input( INPUT_POST, 'new_project', FILTER_VALIDATE_INT, array( 'options' => array( 'default' => 0 )));
$t_final_status = filter_input( INPUT_POST, 'final_status', FILTER_VALIDATE_BOOLEAN, array( 'options' => array( 'default' => false )));
$t_new_status = filter_input( INPUT_POST, 'new_status', FILTER_VALIDATE_INT, array( 'options' => array( 'default' => false, 'min_range' => 10, 'max_range' => 999 )));
$t_new_user = filter_input( INPUT_POST, 'new_user', FILTER_SANITIZE_STRING );
$t_show = filter_input( INPUT_POST, 'show', FILTER_VALIDATE_REGEXP, array( 'options' => array( 'default' => 'status', 'regexp' => '/^status|all$/' )));
$t_show_status = true;
if( $t_show == 'all' ) {
	$t_show_status = false;
}
if( !empty( $t_duration )) {
	switch( $t_duration ) {
		case 4:	// arbitrary dates
			if( $t_start_date !== null && $t_start_date !== 0 ) {
				$date = explode( "-", $t_start_date );
				$start_date = date( 'Y-m-d', mktime( 0, 0, 0, $date[ 1 ], $date[ 0 ], $date[ 2 ]));
				$start_timestamp = mktime( 0, 0, 0, $date[ 1 ], $date[ 0 ], $date[ 2 ]);
			}
			if( $t_end_date !== null && $t_end_date !== 0 ) {
				$date = explode( "-", $t_end_date );
				$end_date = date( 'Y-m-d', mktime( 0, 0, 0, $date[ 1 ], $date[ 0 ], $date[ 2 ]));
				$end_timestamp = mktime( 23, 59, 59, $date[ 1 ], $date[ 0 ], $date[ 2 ]);
			}
			$period_string = "$start_date to $end_date";
			break;
		case 3:	// last 30 days
			$start_timestamp = $month_start;
			$end_timestamp = $today;
			$period_string = "Last 30 Days";
			break;
		case 2:	// last 7 days
			$start_timestamp = $week_start;
			$end_timestamp = $today;
			$period_string = "Last 7 Days";
			break;
		case 1:	// yesterday
		default:
			break;
	}
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title>MantisBT Issue History Report</title>
	
	<style>
		@font-face
		{
			font-family: sans-serif;
			font-size: 0.75em;
		}
		body
		{
			font-family: sans-serif;
			font-size: 0.75em;
			border: 0 none;
			margin: 0;
		}
		.head
		{
			background-color: #66CF05;
		}
		h1, h2, h3, h4, h5, h6, address
		{
			background-color: #66CF05;
			color: #FFFFFF;
			padding: 1px;
			text-align: left;
		}
		h1
		{
			text-align: right;
		}
		table
		{
			border-collapse:collapse;
			width: 95%;
			margin-left: auto;
			margin-right: auto;
			background-color: #549700;
			color: #FFFFFF;
		}
		table, td, th
		{
			border:1px solid black;
		}
        .th a:link {
            color: #FFFFFF;
        }
        .th a:visited {
            color: #BBBBBB;
        }
		.tr1
		{
			background-color: #AEFA68;
            color: black;
		}
		.tr2
		{
			background-color: #79F605;
            color: black;
		}
	</style>
	<style media="print">
		form
		{
			display: none;
		}
		table
		{
			border-collapse:collapse;
			width: 100%;
		}
	</style>
	<style media="tty">
		.head
		{
			background-color: #66CF05;
		}
		h1, h2, h3, h4, h5, h6, address
		{
			background-color: #66CF05;
			color: #FFFFFF;
			padding: 1px;
			text-align: left;
		}
		.th
		{
			background-color: #549700;
			color: #FFFFFF;
		}
		.tr1
		{
			background-color: #AEFA68;
		}
		.tr2
		{
			background-color: #79F605;
		}
	</style>
	<script language="javascript" type="text/javascript" src="/js/datetimepicker.js">
		// Date Time Picker by TengYong Ng of http://www.rainforestnet.com
	</script>
	<script language="javascript" type="text/javascript">
		function finalStatus()
		{
			if (document.getElementById('final_status').checked==true)
				{
				document.getElementById('new_status').setAttribute('disabled','disabled');
				document.getElementById('new_status').value='';
				}
			else
				{
				document.getElementById('new_status').removeAttribute('disabled');
				}
		}
	</script>
</head>
<body>
	<div>
		<div class="head">
			<h1>GGP Systems Limited</h1>
<?php echo "			<h2>$period_string in MantisBT $version_string</h2>" . PHP_EOL; ?>
		</div>
		<div>
			<form method="post" action="index.php">
				<fieldset>
					<legend>Report Filters</legend>
					<label for="show">Show</label><br/>
					<input name="show" id="showStatus" type="radio" onclick="document.getElementById('showAll').checked=false;" value="status"<?php echo ( $t_show_status === true ) ? "checked" : "";?>>Status changes only</input>&nbsp;<input name="show" id="showAll" type="radio" onclick="document.getElementById('showStatus').checked=false;" value="all"<?php echo ( $t_show_status === false ) ? "checked" : "";?>>All changes</input><br/>
					<label for="duration">Duration</label><br/>
					<select name="duration" id="duration">
						<option value="1" onclick="document.getElementById('arbitraryDates').disabled=true; document.getElementById('start_date').value=''; document.getElementById('end_date').value='';"<?php echo ( $t_duration == 1 ) ? ' selected' : '';?>>Yesterday</option>
						<option value="2" onclick="document.getElementById('arbitraryDates').disabled=true; document.getElementById('start_date').value=''; document.getElementById('end_date').value='';"<?php echo ( $t_duration == 2 ) ? ' selected' : '';?>>Last 7 days</option>
						<option value="3" onclick="document.getElementById('arbitraryDates').disabled=true; document.getElementById('start_date').value=''; document.getElementById('end_date').value='';"<?php echo ( $t_duration == 3 ) ? ' selected' : '';?>>Last 30 days</option>
						<option value="4" onclick="document.getElementById('arbitraryDates').disabled=false;"<?php echo ( $t_duration == 4 ) ? ' selected' : '';?>>Arbitrary dates</option>
					</select>
					<fieldset id="arbitraryDates" disabled="disabled">
						<label for="start_date">Start Date</label><br/>
						<input name="start_date" id="start_date" type="text" <?php echo ( $t_start_date !== 0 ) ? "value='$t_start_date'" : "";?>/>
						<a href="javascript:NewCal ('start_date','ddMMyyyy')"><img src='/js/cal.gif' alt='Start Date' width='16' height='16' /></a><br/>
						<label for="end_date">End Date</label><br/>
						<input name="end_date" id="end_date" type="text" <?php echo ( $t_start_date !== 0 ) ? "value='$t_start_date'" : "";?>/>
						<a href="javascript:NewCal ('end_date','ddMMyyyy')"><img src='/js/cal.gif' alt='End Date' width='16' height='16' /></a><br/>
					</fieldset>
					<label for="new_project">Filter by Project</label><br/>
					<select name="new_project" id="new_project">
						<option value="0"<?php echo ( $t_new_project===0 ) ? "selected" : "";?>>All Projects</option>
<?php $dbh_mantisbt = new mysqli( $dsn[ 'hostspec' ], $dsn[ 'username' ], 
		$dsn[ 'password' ], $dsn[ 'database' ] );
if ( $dbh_mantisbt->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_mantisbt->connect_error() );
}

// Populate projects drop-down here...
$query = "SELECT p.id, p.name, ph.parent_id "
		. "FROM $mpt_name p "
		. "LEFT JOIN $mpult_name u "
		. "ON p.id = u.project_id AND u.user_id = 2 "
		. "LEFT JOIN $mpht_name ph "
		. "ON ph.child_id = p.id "
		. "WHERE (p.view_state = 10 "
		. "OR (p.view_state = 50 "
		. "AND u.user_id = 2)) "
		. "ORDER BY p.name";
$result = $dbh_mantisbt->query( $query );
while( $row = $result->fetch_assoc() ) {
	$project_name_arr[ (int) $row[ 'id' ]] = $row[ 'name' ];
	$projects_arr[ (int) $row[ 'id' ]] = ( (int) $row[ 'parent_id' ] === NULL ) ? 0 : (int) $row[ 'parent_id' ];
}
unset($row); $result->close();
foreach( $projects_arr as $id => $parent ) {
	if(( $parent !== 0 ) && isset( $projects_arr[ $parent ])) {
		$prune_arr[] = $id;
	}
}
foreach( $prune_arr as $id ) {
	unset( $projects_arr[ $id ]);
}
$projects_arr = array_keys( $projects_arr );

// Parent projects
$project_count = count( $projects_arr );
for( $i = 0; $i < $project_count; $i++ ) {
	$id = $projects_arr[ $i ];
	if( (int) project_get_field( $dbh_mantisbt, $id, 'enabled' ) == 1 ) {
		echo "						<option value='$id'";
		check_selected( $t_new_project, $id );
		echo ">" . project_get_field( $dbh_mantisbt, $id, 'name' ) . "</option>" . PHP_EOL;
		// Subprojects
		print_subproject_list( $dbh_mantisbt, $id, $t_new_project, null, true, array());
	}
}
?>
					</select>
					<br/>
					<label for="new_status">Filter by Status</label><br/>
					<select name="new_status" id="new_status">
						<option value="0"<?php echo ( !$t_new_status ) ? " selected" : "";?>>&nbsp;</option>
<?php // Populate status drop-down here...
foreach( $status_ref_arr as $key => $value ) {
	echo "						<option value='$key'";
	echo ( $t_new_status === $key ) ? " selected" : "";
	echo ">$value</option>" . PHP_EOL;
}
?>
					</select>
					&nbsp;
					<label for="final_status">Show final status only</label>
					<input type="checkbox" name="final_status" id="final_status" <?php echo ( $t_final_status ) ? "checked" : "";?>/>
					<br/>
					<label for="new_user">Filter by User</label><br/>
					<select name="new_user" id="new_user">
						<option value="0"<?php echo ( !$t_new_user ) ? " selected" : "";?>>&nbsp;</option>
						<option value="1"<?php echo ( $t_new_user ) ? " selected" : "";?>><i>QA/Testers</i></option>
<?php // Populate user drop-down here...
foreach( $tester_ref_arr as $key => $value ) {
	echo "						<option value='$key'";
	echo ( $t_new_user === $key ) ? " selected" : "";
	echo ">$value</option>" . PHP_EOL;
}
?>
					</select>
				</fieldset>
				<p>
					<input type="submit" name="action" value="Update" />
					<input type="hidden" name="nonce" value="9IVxVpOx2eCzQAZS" />
				</p>
			</form>
		</div>
		<p>
<?php $type_ref_arr = array(
	0=>'Normal type',
	1 => 'New issue',
	2 => 'Note added',
	3 => 'Note edited',
	4 => 'Note deleted',
	6 => 'Description updated',
	7 => 'Additional information updated',
	8 => 'Steps to Reproduce updated',
	9 => 'File added',
	10 => 'File deleted',
	11 => 'Note view state',
	12 => 'Issue monitored',
	13 => 'Issue end monitor',
	14 => 'Issue deleted',
	15 => 'Sponsorship added',
	16 => 'Sponsorship updated',
	17 => 'Sponsorship deleted',
	18 => 'Relationship added',
	19 => 'Relationship deleted',
	20 => 'Issue cloned',
	21 => 'Issue generated from',
	22 => 'Checkin',
	23 => 'Relationship replaced',
	24 => 'Sponsorship paid',
	25 => 'Tag attached',
	26 => 'Tag detached',
	27 => 'Tag renamed',
	28 => 'Bug revision dropped',
	29 => 'Note revision dropped',
	100 => 'Changeset attached',
);

$priority_ref_arr = array(
	10 => 'None',
	20 => 'Low',
	30 => 'Normal',
	40 => 'High',
	50 => 'Urgent',
	60 => 'Immediate',
);

$relationship_ref_arr = array(
	0 => 'duplicate of',
	1 => 'related to',
	2 => 'parent of',
	3 => 'child of',
	4 => 'has duplicate',
);

$reproducibility_ref_arr = array(
	10 => 'always',
	30 => 'sometimes',
	35 => 'rarely',
	40 => 'not seen again',
	70 => 'have not tried',
	80 => 'customer only',
	100 => 'N/A',
);

$resolution_ref_arr = array(
	10 => 'open',
	20 => 'fixed',
	50 => 'not fixable',
	60 => 'duplicate',
	70 => 'no change required',
	75 => 'work-around',
	80 => 'suspended',
	90 => 'won\'t fix',
);

$t_query = "SELECT * FROM $mbht_name WHERE";
if( $t_show_status ) {
	$t_query .= " field_name='status' AND";
}
$t_query .= " date_modified BETWEEN $start_timestamp AND $end_timestamp";
if( $t_new_status ) {
	if( $t_new_status < 999 ) {
		$t_query .= ' AND new_value=' . $t_new_status;
	} else {
		$t_query .= ' AND (new_value=25 OR new_value=90)';
	}
}
$t_query .= ';';
$result = $dbh_mantisbt->query( $t_query );
while( $row = $result->fetch_assoc()) {
	$user_id_arr[] = (int) $row[ 'user_id' ];
	$bug_id_arr[] = (int) $row[ 'bug_id' ];
	$field_name_arr[] = $row[ 'field_name' ];
	$old_value_arr[] = (int) $row[ 'old_value' ];
	$new_value_arr[] = (int) $row[ 'new_value' ];
	$type_arr[] = $row[ 'type' ];
	$date_modified_arr[] = $row[ 'date_modified' ];
}
unset( $row ); $result->close();

$issue_arr = array_unique( $bug_id_arr, SORT_NUMERIC );
sort( $issue_arr, SORT_NUMERIC );

foreach( array_unique( $user_id_arr, SORT_NUMERIC ) as $user_id ) {
	$result = $dbh_mantisbt->query( "SELECT id, realname FROM $mut_name WHERE 1;" );
	while( $row = $result->fetch_assoc()) {
		$user_name_arr[ $row[ 'id' ]] = $row[ 'realname' ];
	}
	unset( $row ); $result->close();
}

if( $t_new_project != 0 ){
	$projects_arr[] = $t_new_project;
	$projects_arr = array_merge( $projects_arr, get_all_accessible_subprojects( $dbh_mantisbt, $t_new_project ));
	sort( $projects_arr, SORT_NUMERIC );
}
foreach( $issue_arr as $bug_id) {
	$t_query = "SELECT id, handler_id, status, summary, date_submitted FROM $mbt_name WHERE id=$bug_id";
	if( $t_new_project != 0 ) {
		$t_query .= " AND project_id IN (" . implode( ',', $projects_arr ) . ")";
	}
	$result = $dbh_mantisbt->query( $t_query );
	while( $row = $result->fetch_assoc()) {
		$t_issue_arr[] = $bug_id;
		$handler_name_arr[ $bug_id ] = $user_name_arr[ $row[ 'handler_id' ]];
		$status_arr[ $bug_id ] = $status_ref_arr[ $row[ 'status' ]];
		$summary_arr[ $bug_id ] = $row[ 'summary' ];
		$date_submitted_arr[ $bug_id ] = date( 'Y-m-d H:i', $row[ 'date_submitted' ]);
	}
	unset( $row ); $result->close();
}
$t_issue_arr = array_unique( $t_issue_arr, SORT_NUMERIC );
$t_mantis_view_bug_url = "http://svn.ggpsystems.co.uk/mantis/view.php?id=";
foreach( $t_issue_arr as $bug_id ) {
	$t_query = "SELECT value FROM $mcfst_name WHERE field_id=16 AND bug_id=$bug_id";
	$result = $dbh_mantisbt->query( $t_query );
	while( $row = $result->fetch_assoc()) {
		$t_tester = $row[ 'value' ];
	}
	unset( $row ); $result->close();
    $t_assigned_tester = '';
    if( $t_tester!='' ) {
        $t_assigned_tester .= ' (' . $tester_ref_arr[ $t_tester ] . ')';
    }
	$t_display_flag = false;
	if( $t_new_user == "0" ) {
		$t_display_flag = true;
	} elseif( $t_new_user == "1" ) {
		if( $handler_name_arr[ $bug_id ] === "Callum Bryson" ||
				$handler_name_arr[ $bug_id ] === "Louis Genasi" ||
				$tester_ref_arr[ $t_tester ] === "Callum Bryson" ||
				$tester_ref_arr[ $t_tester ] === "Louis Genasi" ) {
			$t_display_flag = true;
		}
	} elseif( $handler_name_arr[ $bug_id ] === $tester_ref_arr[ $t_new_user ] || 
			$t_tester === $t_new_user ) {
		$t_display_flag = true;
	}
	if( $t_display_flag ) {
		// Need to search for both Callum and Louis
		echo '			<table>' . PHP_EOL;
		echo '				<tbody>' . PHP_EOL;
		echo '					<tr class="th">' . PHP_EOL;
		echo '						<td style="width: 25%"><strong>ID</strong>: <a class="th" href="' . $t_mantis_view_bug_url . $bug_id . '">' . $bug_id . '</a></td>' . PHP_EOL;
		echo '						<td style="width: 25%"><strong>Assigned To</strong>: ' . $handler_name_arr[ $bug_id ] . $t_assigned_tester . '</td>' . PHP_EOL;
		echo '						<td style="width: 25%"><strong>Status</strong>: ' . $status_arr[ $bug_id ] . '</td>' . PHP_EOL;
		echo '						<td style="width: 25%"><strong>Date Submitted</strong>: ' . $date_submitted_arr[ $bug_id ] . '</td>' . PHP_EOL;
		echo '					</tr>' . PHP_EOL;
		echo '					<tr>' . PHP_EOL;
		echo '						<td colspan="4" class="tr1"><strong>Summary</strong>: ' . $summary_arr[ $bug_id ] . '</td>' . PHP_EOL;
		echo '					</tr>' . PHP_EOL;
?>
					<tr class="th">
						<th>Date Modified</th>
						<th>Username</th>
						<th>Field</th>
						<th>Change</th>
					</tr>
<?php	$bug_history_arr = array_keys( $bug_id_arr, $bug_id );
		$t_mantis_url = "http://svn.ggpsystems.co.uk/mantis/bug_revision_view_page.php?";
		$i = 1;
		if( $t_final_status ) {
			$bug_history_arr = array_reverse( $bug_history_arr );
		}
		foreach( $bug_history_arr as $bug_history_id ) {
			echo '					<tr class="tr' . $i . '">' . PHP_EOL;
			echo "						<td>" . date( 'Y-m-d H:i', $date_modified_arr[ $bug_history_id ]) . "</td>" . PHP_EOL;
			echo "						<td>" . $user_name_arr[ $user_id_arr[ $bug_history_id ]] . "</td>" . PHP_EOL;
			$t_field_name = str_replace( '_', ' ', ucfirst( $field_name_arr[ $bug_history_id ]) );
			switch( $type_arr[ $bug_history_id ] ) {
				case 0:	// Normal type changes
					switch( $field_name_arr[ $bug_history_id ] ) {
						case 'Actual Result':
						case 'Customer Defect ID':
						case 'Customer Priority':
						case 'Customer Severity':
						case 'Documentation Update Required':
						case 'Documentation Updated Description':
						case 'Expected Result':
						case 'Reported in Revision':
						case 'Resolution Details':
						case 'Sugar Case Number':
						case 'summary':
						case 'target_version':
						case 'Test Cases':
						case 'Tested in Revision':
							$t_changes = htmlentities( $old_value_arr[ $bug_history_id ]) . " => " . htmlentities( $new_value_arr[ $bug_history_id ]);
							break;
						case 'Assigned Tester':
							$t_changes = $tester_ref_arr[ $old_value_arr[ $bug_history_id ]] . " => " . $tester_ref_arr[ $new_value_arr[ $bug_history_id ]];
							break;
						case 'fixed_in_version':
							$t_changes = $old_value_arr[ $bug_history_id ] . " => " . $new_value_arr[ $bug_history_id ];
							break;
						case 'handler_id':
							$t_field_name = 'Assigned to';
							$t_changes = $user_name_arr[ $old_value_arr[ $bug_history_id ]] . " => " . $user_name_arr[ $new_value_arr[ $bug_history_id ]];
							break;
						case 'priority':
							$t_changes = ucfirst( $priority_ref_arr[ $old_value_arr[ $bug_history_id ]]) . " => " . ucfirst( $priority_ref_arr[ $new_value_arr[ $bug_history_id ]]);
							break;
						case 'project_id':
							$t_field_name = 'Project';
							$t_changes = $project_name_arr[ $old_value_arr[ $bug_history_id ]] . " => " . $project_name_arr[ $new_value_arr[ $bug_history_id ]];
							break;
						case 'reproducibility':
							$t_changes = ucfirst( $reproducibility_ref_arr[ $old_value_arr[ $bug_history_id ]]) . " => " . ucfirst( $reproducibility_ref_arr[ $new_value_arr[ $bug_history_id ]]);
							break;
						case 'resolution':
							$t_changes = ucfirst( $resolution_ref_arr[ $old_value_arr[ $bug_history_id ]]) . " => " . ucfirst( $resolution_ref_arr[ $new_value_arr[ $bug_history_id ]]);
							break;
						default:
							$t_changes = ucfirst( $status_ref_arr[ $old_value_arr[ $bug_history_id ]]) . " => " . ucfirst( $status_ref_arr[ $new_value_arr[ $bug_history_id ]]);
					}
					break;
				case 2:	// Note added
				case 3:	// Note edited
				case 4:	// Note deleted
					$t_bugnote_id = ( int )ltrim( $old_value_arr[ $bug_history_id ], '0' );
					$t_revision_id = ( int )$new_value_arr[ $bug_history_id ];
					$t_field_name = $type_ref_arr[ $type_arr[ $bug_history_id ]] . ": " . $old_value_arr[ $bug_history_id ];
					if( $type_arr[ $bug_history_id ] == 3 ) {
						$t_changes = "<a href='{$t_mantis_url}bugnote_id={$t_bugnote_id}#r{$t_revision_id}'>View revisions</a>";
					} else {
						$t_changes = "&nbsp";
					}
					break;
				case 6:	// Description updated
				case 7:	// Additional Information updated
				case 8:	// Steps to Reproduce updated
					$t_field_name = $type_ref_arr[ $type_arr[ $bug_history_id ]];
					$t_changes = "<a href='{$t_mantis_url}rev_id={$old_value_arr[ $bug_history_id ]}#r{$old_value_arr[ $bug_history_id ]}'>View revisions</a>";
					break;
				case 12: // Issue monitored
				case 13: // Issue end monitor
					$t_field_name = $type_ref_arr[ $type_arr[ $bug_history_id ]];
					$t_changes = $user_name_arr[ $old_value_arr[ $bug_history_id ]];
					break;
				case 18:	// Relationship added
				case 19:	// Relationship deleted
				case 23:	// Relationship replaced
					$t_new_relationship_value = sprintf( '%07d', ( int )$new_value_arr[ $bug_history_id ]);
					$t_field_name = $type_ref_arr[ $type_arr[ $bug_history_id ]];
					$t_changes = ucfirst( $relationship_ref_arr[ $old_value_arr[ $bug_history_id ]]) . " " . $t_new_relationship_value;
					break;
				case 100:	// SCI changeset
					$t_action = array_pop( explode( ' ', $t_field_name ));
					switch( $t_action ) {
						case "attached":
							$t_changes = $new_value_arr[ $bug_history_id ];
							break;
						case "removed":
							$t_changes = $old_value_arr[ $bug_history_id ];
							break;
					}
					break;
				default:
					$t_field_name = $type_ref_arr[ $type_arr[ $bug_history_id ]];
					$t_changes = $old_value_arr[ $bug_history_id ];
			}
			// The future - make two variables and have a single pair of echos
			echo "						<td>" . ucfirst( $t_field_name ) . "</td>" . PHP_EOL;
			echo "						<td>" . $t_changes . "</td>" . PHP_EOL;
			echo '					</tr>' . PHP_EOL;
			( $i==1 ? $i++ : $i-- );
			if( $t_final_status ) {
				break;
			}
		}
?>
				</tbody>
			</table>
			<br/>
		</p>
	</div>
</body>
</html>
<?php }
}

function check_selected( $p_var, $p_val = true ) {
	if( is_string( $p_var ) && is_string( $p_val )) {
		if( $p_var === $p_val ) {
			echo ' selected';
			return;
		}
	} else if( $p_var == $p_val ) {
		echo ' selected';
		return;
	}
}

/**
 * 
 * @param mixed $p_dbh
 * @param int $p_project_id
 * @return array
 */
function get_accessible_subprojects( $p_dbh, $p_project_id ) {
	$mpt_name = 'mantis_project_table';
	$mpht_name = 'mantis_project_hierarchy_table';
	$t_projects = array();

	$result = $p_dbh->query( "SELECT DISTINCT p.id, p.name, ph.parent_id "
			. "FROM $mpt_name p "
			. "LEFT JOIN $mpht_name ph "
			. "ON ph.child_id = p.id "
			. "WHERE p.enabled = 1 "
			. "AND ph.parent_id IS NOT NULL "
			. "ORDER BY p.name" );
	while( $row = $result->fetch_assoc() ) {
		if( !isset( $t_projects[ (int) $row[ 'parent_id' ]])) {
			$t_projects[ (int) $row[ 'parent_id' ]] = array();
		}
		array_push( $t_projects[ (int) $row[ 'parent_id' ]], (int) $row[ 'id' ]);
	}
	unset( $row ); $result->close();

	return $t_projects[ (int) $p_project_id];
}

/**
 * 
 * @param mixed $p_dbh
 * @param int $p_project_id
 * @return array
 */
function get_all_accessible_subprojects( $p_dbh, $p_project_id ) {
	$t_todo = get_accessible_subprojects( $p_dbh, $p_project_id );
	$t_subprojects = array();
	
	while( $t_todo ) {
		$t_elem = (int) array_shift( $t_todo );
		if( !in_array( $t_elem, $t_subprojects )){
			array_push( $t_subprojects, $t_elem );
			$t_todo = array_merge( $t_todo, get_all_accessible_subprojects($p_dbh, $t_elem));
		}
	}
	
	return $t_subprojects;
}

/**
 * 
 * @param mixed $p_dbh
 * @param int $p_parent_id
 * @param int $p_project_id
 * @param int $p_filter_project_id
 * @param bool $p_trace
 * @param array $p_parents
 */
function print_subproject_list( $p_dbh, $p_parent_id, $p_project_id = null, $p_filter_project_id = null, $p_trace = false, $p_parents = Array()) {
	array_push( $p_parents, $p_parent_id );
	$t_project_ids = get_accessible_subprojects($p_dbh, $p_parent_id);
	$t_project_count = count( $t_project_ids );
	for( $i = 0; $i < $t_project_count; $i++ ){
		$t_full_id = $t_id = $t_project_ids[ $i ];
		if( $t_id != $p_filter_project_id ) {
			echo "						<option value='$t_full_id'";
			check_selected( $p_project_id, $t_full_id );
			echo ">"
				. str_repeat( '&nbsp;', count( $p_parents )) 
				. str_repeat( '&raquo;', count( $p_parents )) . ' '
				. project_get_field($p_dbh, $t_id, 'name' ) . '</option>' . PHP_EOL;
				print_subproject_list( $p_dbh, $t_id, $p_project_id, $p_filter_project_id, $p_trace, $p_parents);
		}
	}
}

/**
 * 
 * @param mixed $p_dbh
 * @param int $p_project_id
 * @param string $p_field_name
 * @return string
 */
function project_get_field( $p_dbh, $p_project_id, $p_field_name ) {
	$mpt_name = 'mantis_project_table';
	
	$result = $p_dbh->query( "SELECT * FROM $mpt_name WHERE id=$p_project_id" );
	while( $row = $result->fetch_assoc()) {
		if( isset( $row[ $p_field_name ])) {
			return $row[ $p_field_name ];
		}
	}
	unset( $row ); $result->close();
	return '';
}
