<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
# $plugin['name'] = 'abc_plugin';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;

$plugin['version'] = '0.2.4';
$plugin['author'] = 'Egypt Urnash';
$plugin['author_uri'] = 'http://egypt.urnash.com/';
$plugin['description'] = 'Enable comments replies';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
# $plugin['order'] = 5;

// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and non-AJAX admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the non-AJAX admin side
// 4 = admin+ajax   : only on admin side
// 5 = public+admin+ajax   : on both the public and admin side
# $plugin['type'] = 0;

// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

# $plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

if (!defined('txpinterface'))
    @include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---

h1. peg_nested_comments

h2. Installation, upgrading and uninstallation

Download the latest version of the plugin, paste the code into the Textpattern Admin → Plugins panel, install and enable the plugin.

To uninstall, delete from the Admin → Plugins panel.

h2. Tags

h3. peg_comments tag

bc. <txp:peg_comments />

In the @comments_display@ form, replace your @<txp:comments />@ with @<txp:peg_comments />@. 

h4. Attributes

Attributes are the same as @<txp:comments />@.
See "here":http://www.textpattern.net/wiki/index.php?title=comments for more informations.

h3. peg_child_comments tag

bc. <txp:peg_child_comments />

in your @comments@ form, add @<txp:peg_child_comments />@ at the end. Duplicate whatever attributes you used for @<txp:peg_comments />@.

h3. peg_reply_link tag

bc. <txp:peg_reply_link />

Also add @<txp:peg_reply_link />@ wherever you see fit; this generates the link to reply to the comment.

h3. peg_reply_to tag

bc. <txp:peg_reply_to />

finally, insert @<txp:peg_reply_to />@ somewhere in your @comment_form@ form.

h4. Attributes

* @wraptag="span"@  _(default: span)_
** used to wrap the generated label, input and links;
* @class=""@
** applied to the wraptag;
* @label="Your label"@ _(default: Replying to)_
** used as the value of the @<label>@ HTML tag;
* @back="Go the initial comment"@ _(default: #)_
** used as a link to the comment you are replying to;
* @close="Unchain"@ _(default: ×)_
** used as for link which allow to break the link to the comment you were replying to;
** add the @.peg_replyto_inactive@ to #peg_replyto_wrap.

h4. Styling

Use @.peg_replyto_active@ and @.peg_replyto_inactive@ classes to to hide or alter the display of this tag content in your comment form. @.peg_replyto_active@ is used on reply, @.peg_replyto_inactive@ on simple comment; the second one should be enough to do the following.

h5. Exemples

Blur the content on simple comment (not a reply)…

bc. .peg_replyto_inactive { opacity: 0.6; }

…or hide it with…

bc. .peg_replyto_inactive { display: none; }

…or…

bc. .peg_replyto_inactive { visibility: hidden; }

h2. Authors/credits

Made Txp4.5 compatible by "Oleg Loukianov":http://www.iut-fbleau.fr/projet/etc/ from a Margaret Trauth's code.
Many thanks to "all additional contributors":https://github.com/bloatware/peg_nested_comments/graphs/contributors.

# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---

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

    // extract($thisarticle);
    if (!$thisarticle['comments_count']) return '';
    $thisid = intval($thisarticle['thisid']);

    // $txp_discuss = safe_pfx('txp_discuss');
    // $peg_discuss = safe_pfx('peg_discuss');
    if(!empty($thiscomment)) {
        $peg_children = $thiscomment['peg_children'];
    } else {
        // safe_query("CREATE TEMPORARY TABLE $peg_discuss (SELECT * FROM $txp_discuss WHERE parentid=$thisid AND visible=".VISIBLE.")");
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
        'label'    => '',
        'wraptag'  => 'span',
        'class'    => '',
        'back'     => '#',
        'close'    => '&times;',
        'size'     => 25
    ),$atts));

    $reply_to = htmlspecialchars(ps('peg_replyto'));
    $reply_to_input = htmlspecialchars(ps('peg_replyto_input'));

    return 
    '<'.$wraptag.' id="peg_replyto_wrap" class="'.$class. ($reply_to?'peg_replyto_active':'peg_replyto_inactive').'">'.n.
    '<label for="peg_replyto_input" id="peg_replyto_label">'.$label.'</label><input type="text" name="peg_replyto_input" id="peg_replyto_input" value="'.$reply_to_input.'" readonly size="'.$size.'" />'.n.
    '<input type="hidden" name="peg_replyto" id="peg_replyto" value="'.$reply_to.'" />'.n.          
      ($back ? '<a id="peg_replyto_backlink" href='.($reply_to?"#c$reply_to":"#").'>'.$back.'</a>' : '').n.
      ($close ? '<a id="peg_replyto_closelink" href="#peg_replyto_input" title="Empty" onclick="return peg_selectComment(null,null,null,this);">'.$close.'</a>' : '').'</'.$wraptag.'>'.n.

    '<script type="text/javascript">
        function peg_selectComment(discussid,name,title,target) {
            var pegSelect = document.getElementById("peg_replyto");
            var pegSelectWrap = document.getElementById("peg_replyto_wrap");
            var pegSelectInput = document.getElementById("peg_replyto_input");
            var pegSelectBack = document.getElementById("peg_replyto_backlink");

            if(pegSelect) pegSelect.value = discussid;
            if(pegSelectWrap) {
                pegSelectWrap.className = (discussid?"peg_replyto_active":"peg_replyto_inactive");
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
    // if (!checkCommentsAllowed($thisarticle['thisid'])) return;
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

# --- END PLUGIN CODE ---

?>
