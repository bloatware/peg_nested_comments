register_callback('peg_saveCommentLinks','comment.save');
register_callback('peg_comments_install', 'plugin_lifecycle.peg_nested_comments');

function peg_comments_install($event='', $step='') {
	$rs = safe_query("show columns from txp_discuss like 'peg_children'");// or die("Error checking tables.");
	if($step == 'deleted') {
		if (numRows($rs) > 0) $rs = safe_alter ('txp_discuss',"DROP peg_children") or die("Error altering tables.");
		return;
	}
	if($step == 'installed') {
		if (numRows($rs) == 0) {
			$rs = safe_alter ('txp_discuss',"ADD peg_children text not null") or die("Error altering tables.");
		}
		return;
	}
}

/*
if (@txpinterface == 'public') {
	// public page stuff would go here
} else {
	// admin page stuff would go here
}
*/

// cut and paste from txp's parse_form
// with the anti-recursion bits removed
function peg_parse_form_recursively($name) {
	$f = fetch_form($name);
	if ($f) {
		// array_push($stack, $name);
		$out = parse($f);
		// array_pop($stack);
		return $out;
	}
}

// provides a nested list of comments
// this started as a direct cut-and-paste of txp's 'comments' function...

function peg_comments($atts) {
  global $thisarticle, $thiscomment, $prefs;

  extract(lAtts(array(
		'form'       => 'comments',
		'wraptag'    => ($prefs['comments_are_ol'] ? 'ol' : ''),
		'break'      => ($prefs['comments_are_ol'] ? 'li' : 'div'),
		'class'      => 'comments',
		'breakclass' => '',
		'limit'      => 0,
		'offset'     => 0,
		'sort'       => 'posted ASC',
	),$atts));

	assert_article();
//	extract($thisarticle);

	if (!$thisarticle['comments_count']) return '';
	$thisid = intval($thisarticle['thisid']);

//	$txp_discuss = safe_pfx('txp_discuss');
//	$peg_discuss = safe_pfx('peg_discuss');
	if(!empty($thiscomment)) {
		$peg_children = $thiscomment['peg_children'];
	} else {
//		safe_query("CREATE TEMPORARY TABLE $peg_discuss (SELECT * FROM $txp_discuss WHERE parentid=$thisid AND visible=".VISIBLE.")");
		$rs = safe_rows('discussid, peg_children', 'txp_discuss', "parentid=$thisid AND visible=".VISIBLE);
		$peg_children = $rows = array();
		foreach($rs as $vars) {
			$rows[] = $vars['discussid'];
			if($vars['peg_children']) $peg_children = array_merge($peg_children, explode(',', $vars['peg_children']));
		}
		$peg_children = implode(',', $peg_children ? array_diff($rows, $peg_children) : $rows);
	}
	if(empty($peg_children)) return '';

	$qparts = "discussid IN($peg_children) AND parentid=$thisid AND visible=".VISIBLE.
	($sort ? " ORDER BY $sort" : '').
	($limit || $offset ? ' LIMIT '.intval($offset).', '.intval($limit) : '');

	$rs = safe_rows('*, unix_timestamp(posted) as time', 'txp_discuss', $qparts);
	$out = '';

	if ($rs) {

		foreach ($rs as $vars) {
			$GLOBALS['thiscomment'] = $vars;
			$comments[] = peg_parse_form_recursively($form).n;
			unset($GLOBALS['thiscomment']);
		}

		$out .= doWrap($comments,$wraptag,$break,$class,$breakclass);
	}

	return $out;
}

function peg_child_comments($atts) {//legacity stuff
	return peg_comments($atts);
}

function peg_saveCommentLinks($event, $step='') {
	$reply_to = htmlspecialchars(ps('peg_replyto'));
	echo $reply_to;
	if ($reply_to != 'Article' && $reply_to != '') {
		$nextID = 'lpad("'.peg_getNextAutoID(safe_pfx('txp_discuss')).'",6,"0")';
		$rs = safe_update('txp_discuss','peg_children = if (peg_children = "", '.$nextID.',  concat_ws(",",peg_children,'.$nextID.'))','discussid='.$reply_to);
	}
}

// this one is straight out of cha_comment_reply
function peg_getNextAutoID($tname) {
	/*
	 * Return the next id to be used on auto_increment in table $tname
	 */

	$status = getRow('show table status like \''.$tname.'\'');
	if (is_array($status)) {
		$ret = $status['Auto_increment'];
	} else {
		$ret = False;
	}

	return $ret;
}

// adds an invisible value to the reply form
// telling us what comment is being replied to
// pretty much swiped from cha_comment_reply

function peg_reply_to($atts) {
	extract(lAtts(array(
		'back'    => '#',
		'label'   => 'Replying to',
		'close'   => '&times;',
		'size'    => 25
	),$atts));

	$reply_to = htmlspecialchars(ps('peg_replyto'));
	$reply_to_input = htmlspecialchars(ps('peg_replyto_input'));

	return 
	'<span id="peg_replyto_wrap" style="'.($reply_to?'':'display:none').'">'.n.
	'<label for="peg_replyto_input" id="peg_replyto_label">'.$label.'</label><input type="text" name="peg_replyto_input" id="peg_replyto_input" value="'.$reply_to_input.'" readonly="readonly" size="'.$size.'" /><input type="hidden" name="peg_replyto" id="peg_replyto" value="'.$reply_to.'" />'.n.	  	
  	($back ? '<a id="peg_replyto_backlink" href='.($reply_to?"#c$reply_to":"#").'>'.$back.'</a>' : '').n.
  	($close ? '<a id="peg_replyto_closelink" href="#peg_replyto_input" title="Empty" onclick="return peg_selectComment(null,null,null,this);">'.$close.'</a>' : '').'</span>'.n.

	'<script type="text/javascript">
		function peg_selectComment(discussid,name,title,target) {
			var pegSelect = document.getElementById("peg_replyto");
			var pegSelectWrap = document.getElementById("peg_replyto_wrap");
			var pegSelectInput = document.getElementById("peg_replyto_input");
			var pegSelectBack = document.getElementById("peg_replyto_backlink");

			if(pegSelect) pegSelect.value = discussid;
			if(pegSelectWrap) {
				pegSelectWrap.setAttribute("style", discussid?"":"display:none");
			}
			if(pegSelectInput) {
				pegSelectInput.value = (name?name:"");
				pegSelectInput.title = (title?title:"");
			}
			if(pegSelectBack) {
				pegSelectBack.href = (discussid?"#c"+discussid:null);
				pegSelectBack.title = (discussid?discussid:"");
			}
			return !!discussid;
		}
	</script>';
}

function peg_reply_link($atts) {
	// our current comment
	global $thisarticle, $thiscomment, $comments_dateformat;
    //	if (!checkCommentsAllowed($thisarticle['thisid'])) return;
	extract(lAtts(array(
		'label'    => 'Reply',
		'size'     => 80
	),$atts));

	$href = "#txpCommentInputForm";

	if($size) {
		$title = preg_replace('/\s+/s', ' ', substr(trim(strip_tags($thiscomment['message'])), 0, $size+16));
		if(strlen($title) > $size+8) {
			$title = substr($title, 0, $size); 
			$title = substr($title, 0, strrpos($title," ")); 
			$title = $title.'...';
		}
	} else $title='';

	$onclick = "return peg_selectComment('".$thiscomment['discussid']."','".htmlspecialchars($thiscomment['name'])." &#183; ".safe_strftime($comments_dateformat, $thiscomment['time'])."','$title', this)";

	$out = "<a class=\"peg_replyto\" href=\"$href\" onclick=\"$onclick\">$label</a>";

	return $out;
}
