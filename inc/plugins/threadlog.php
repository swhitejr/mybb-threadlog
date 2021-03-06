<?php

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}


function threadlog_info()
{
    return array(
        "name" => "Threadlog",
        "description" => "Creates a threadlog for users",
        "website" => "http://autumnwelles.com/",
        "author" => "Autumn Welles",
        "authorsite" => "http://autumnwelles.com/",
        "version" => "3.0",
        "guid" => "",
        "codename" => "threadlog",
        "compatibility" => "18*"
    );
}

function threadlog_install()
{
    global $db, $mybb;

    // alter the forum table
    $db->write_query("ALTER TABLE `" . $db->table_prefix . "forums` ADD `threadlog_include` TINYINT( 1 ) NOT NULL DEFAULT '0'");

    // Generate a new table to store order and description on a per user basis
    $db->write_query("CREATE TABLE `" . $db->table_prefix . "threadlog` (
        ltid INTEGER NOT NULL, 
        luid INTEGER NOT NULL, 
        torder INTEGER DEFAULT 9999,
        thidden BOOLEAN DEFAULT FALSE,
        description VARCHAR(140) DEFAULT '',
        PRIMARY KEY (ltid, luid)
    );");


    // SETTINGS

    // make a settings group
    $setting_group = array(
        'name' => 'threadlog_settings',
        'title' => 'Threadlog Settings',
        'description' => 'Modify settings for the threadlog plugin.',
        'disporder' => 5,
        'isdefault' => 0,
    );

    // get the settings group ID
    $gid = $db->insert_query("settinggroups", $setting_group);

    // define the settings
    $settings_array = array(
        'threadlog_perpage' => array(
            'title' => 'Threads per page',
            'description' => 'Enter the number of threads that should display per page.',
            'optionscode' => 'text',
            'value' => 50,
            'disporder' => 2,
        ),
    );

    // add the settings
    foreach ($settings_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }

    // rebuild
    rebuild_settings();

    // TEMPLATES

    // define the page template
    $threadlog_page = '<html>
        <head>
            <title>{$mybb->settings[\'bbname\']} - Threadlog</title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <div class="Fluid">
                <div class="Container__wrapper">
                    <div class="Container__ProfileHeader">
                        <div class="Container">
                            <div class="Container__wrapper--no-padding Profile__user">
                                <div class="Container__UserBannerAvatar">
                                    <div class="User__banner" style="background-image: url({$user[\'fid35\']})"></div>
                                    <div class="User__avatar">
                                        <img src="{$user[\'avatar\']}" onerror="this.src=\'https://passingstrange.net/images/default_avatar.png\'">
                                    </div>
                                    <div class="User__name">
                                        {$user[\'username\']}
                                    </div>
                                </div>
                                <div class="Container__footer flexbox">
                                    <a href="#" id="active">{$count_active} active</a> &middot;
                                    <a href="#" id="closed">{$count_closed} closed</a> &middot;
                                    <a href="#" id="need-replies">{$count_replies} need replies</a> &middot;
                                    <a href="#" id="show-all">{$count_total} total</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {$multipage}
					<form method="POST" action="/misc.php?action=threadlog">
						<table id="threadlog" class="tborder" border="0" cellpadding="{$theme[\'tablespace\']}"
							cellspacing="{$theme[\'borderwidth\']}" style="width:100%">
							<thead>
								<tr>
									{$threadlog_order_header}
									<td class="tcat">Thread</td>
									<td class="tcat" align="center">Participants</td>
									<td class="tcat" align="center">Posts</td>
									<td class="tcat" align="right">Last Post</td>
								</tr>
							</thead>
							<tbody>
								{$threadlog_list}
							</tbody>
						</table>
						<div class="Container" style="text-align:center; margin-top:10px;">
							{$threadlog_save_button}
						</div>
					</form>
                    {$multipage}
                </div>
            </div>
            {$footer}
            <script type="text/javascript" src="{$mybb->settings[\'bburl\']}/inc/plugins/threadlog/threadlog.js"></script>
        </body>
    </html>';

    // create the page template
    $insert_array = array(
        'title' => 'threadlog_page',
        'template' => $db->escape_string($threadlog_page),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // insert the page template into DB
    $db->insert_query('templates', $insert_array);

    // define the row template
    $threadlog_row = '<tr class="{$thread_status}">
        <td class="{$thread_row}">
            {$thread_prefix} {$thread_title} 
            <div>
                <i>{$thread[\'description\']}</i>
            </div>
            <div class="smalltext">on {$thread_date}</div>
        </td>
        <td class="{$thread_row}" align="center">{$thread_participants}</td>
        <td class="{$thread_row}" align="center"><a href="javascript:MyBB.whoPosted({$tid});">{$thread_posts}</a></td>
        <td class="{$thread_row}" align="right">Last post by {$thread_latest_poster}
            <div class="smalltext">on {$thread_latest_date}</div>
        </td>
    </tr>';

    // create the row template
    $insert_array = array(
        'title' => 'threadlog_row',
        'template' => $db->escape_string($threadlog_row),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // insert the list row into DB
    $db->insert_query('templates', $insert_array);

    // define the no threads row template
    $threadlog_nothreads = "<tr><td colspan='4'>No threads to speak of.</td></tr>";

    // create the no threads row template
    $insert_array = array(
        'title' => 'threadlog_nothreads',
        'template' => $db->escape_string($threadlog_nothreads),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // insert the no threads row into DB
    $db->insert_query('templates', $insert_array);

    // Row Ordering template
    $threadlog_row_order = '<td class="{$thread_row}">
        <input type="text" size="1" name="torder[{$thread[\'tid\']}]" value="{$thread[\'torder\']}" />
    </td>';

    // Create row orderign template
    $insert_array = array(
        'title' => 'threadlog_row_order',
        'template' => $db->escape_string($threadlog_row_order),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // Insert row ordering template
    $db->insert_query('templates', $insert_array);

    // Save button template
    $threadlog_save_button = '<input type="submit" value="Save Threads" class="button" />';

    // Create save button template
    $insert_array = array(
        'title' => 'threadlog_save_button',
        'template' => $db->escape_string($threadlog_save_button),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // Insert save button template
    $db->insert_query('templates', $insert_array);

    // Order Header template
    $threadlog_save_button = '<td class="tcat" align="center">Order</td>';

    // Create Order Header template
    $insert_array = array(
        'title' => 'threadlog_order_header',
        'template' => $db->escape_string($threadlog_save_button),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // Insert Order Header template
    $db->insert_query('templates', $insert_array);

    // Description Input template
    $threadlog_description_input = '<tr class="{$thread_status}">
        <td colspan="5" class="{$thread_row}">
            <input type="text" 
                   name="description[{$thread[\'tid\']}]" 
                   value="{$thread[\'description\']}"
                   placeholder="Description"
                   style="width: 99%"/>
        </td>
    </tr>
    <tr class="{$thread_status}">
        <td colspan="5" class="{$thread_row}">
            <if $thread[\'thidden\'] then>
                <input type="checkbox"
                       name="thidden[{$thread[\'tid\']}]"
                       checked
                       />
            <else>
                <input type="checkbox"
                       name="thidden[{$thread[\'tid\']}]"
                       />
            </if>
            <label for="thidden[{$thread[\'tid\']}]">Hide "{$thread_title}" from threadlog?</label>
        </td>
    </tr>';

    // Create Description Input template
    $insert_array = array(
        'title' => 'threadlog_order_header',
        'template' => $db->escape_string($threadlog_description_input),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // Insert Description Input template
    $db->insert_query('templates', $insert_array);

    // Edit Link template
    $threadlog_edit_link = '<a href="/misc.php?action=threadlog&edit=1">Edit Threadlog</a> &middot;';

    // Create Edit Link template
    $insert_array = array(
        'title' => 'threadlog_order_header',
        'template' => $db->escape_string($threadlog_edit_link),
        'sid' => '-1',
        'version' => '',
        'dateline' => time(),
    );

    // Insert Edit Link template
    $db->insert_query('templates', $insert_array);
}

function threadlog_is_installed()
{
    global $db, $mybb;
    if ($db->field_exists("threadlog_include", "forums") &&
        isset($mybb->settings['threadlog_perpage']) &&
        $db->table_exists("threadlog")) {
        return true;
    }
    return false;
}

function threadlog_uninstall()
{
    global $db;

    // delete forum option
    $db->write_query("ALTER TABLE `" . $db->table_prefix . "forums` DROP `threadlog_include`;");

    // delete threadlog table
    $db->write_query("DROP TABLE IF EXISTS " . $db->table_prefix . "threadlog");

    // delete settings
    $db->delete_query('settings', "name IN ('threadlog_perpage')");

    // delete settings group
    $db->delete_query('settinggroups', "name = 'threadlog_settings'");

    // delete templates
    $db->delete_query("templates", "title IN ('threadlog_page','threadlog_row','threadlog_nothreads')");

    // rebuild
    rebuild_settings();
}

function threadlog_activate()
{

}

function threadlog_deactivate()
{

}

$plugins->add_hook('fetch_wol_activity_end', 'threadlog_wol');
function threadlog_wol($user_activity)
{
    global $parameters;
    if ($parameters['action'] == "threadlog") {
        $user_activity['activity'] = "threadlog";
        $user_activity['uidParam'] = $parameters['uid'];
    }
    return $user_activity;
}

$plugins->add_hook('build_friendly_wol_location_end', 'threadlog_friendly_loc');
function threadlog_friendly_loc($array)
{
    global $parameters;
    if ($array['user_activity']['activity'] == "threadlog") {
        $uid = $array['user_activity']['uidParam'];
        $array['location_name'] = "Viewing <a href='/misc.php?action=threadlog&uid={$uid}'>Thread Log</a>";
    }
    return $array;
}

// this is the main beef, right here
$plugins->add_hook('misc_start', 'threadlog');

function threadlog()
{
    global $mybb, $templates, $theme, $lang, $header, $headerinclude, $footer, $uid, $tid;

    // show the threadlog when we call it
    if ($mybb->get_input('action') == 'threadlog') {
        global $mybb, $db, $templates;

        $templatelist = "multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

        // check for a UID
        if (isset($mybb->input['uid'])) {
            $uid = intval($mybb->input['uid']);
        } // if no UID, show logged in user
        elseif (isset($mybb->user['uid'])) {
            $uid = $mybb->user['uid'];
        } else {
            exit;
        }

        // Handle Post requests
        threadlog_post($uid);

        $editing = isset($mybb->input['edit']) && $uid == $mybb->user['uid'];

        // get the username and UID of current user
        $userquery = $db->write_query("SELECT * FROM `" . $db->table_prefix . "users` as users LEFT JOIN `" . $db->table_prefix . "userfields` as fields ON users.uid = fields.ufid where uid = " . $uid);

        // Get the user object
        $user = $db->fetch_array($userquery);
        $user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);

        // make sure single quotes are replaced so we don't muck up queries
        $username = str_replace("'", "&#39;", $user['username']);

        // add the breadcrumb
        add_breadcrumb($username . '\'s Threadlog', "misc.php?action=threadlog");
        if ($editing) {
            add_breadcrumb('Edit Threadlog', "misc.php?action=threadlog&uid=" . $user['uid'] . "&edit=1");
        }

        // set up this variable, idk why?
        $threads = "";

        // get threads that this user participated in
        $query = $db->simple_select("posts", "DISTINCT tid", "uid = " . $uid . "");
        $topics = "";

        // build our topic list
        while ($row = $db->fetch_array($query)) {
            $topics .= $row['tid'] . ",";
        }

        // remove last comma
        $topics = substr_replace($topics, "", -1);

        // set up topics query
        if (isset($topics)) {
            $tids = " AND tid IN ('" . str_replace(',', '\',\'', $topics) . "')";
        } else {
            $tids = "";
        }

        // get the list of forums to include
        $query = $db->simple_select("forums", "fid", "threadlog_include = 1");
        if ($db->num_rows($query) < 1) {
            $forum_select = " ";
        } else {
            $i = 0;
            while ($forum = $db->fetch_array($query)) {
                $i++;
                if ($i > 1) {
                    $fids .= ",'" . $forum['fid'] . "'";
                } else {
                    $fids .= "'" . $forum['fid'] . "'";
                }
            }
            $forum_select = " AND fid IN(" . $fids . ")";
        }

        // set up the pager
        $threadlog_url = htmlspecialchars_uni("misc.php?action=threadlog&uid=" . $uid);
        if ($editing) {
            $threadlog_url = htmlspecialchars_uni("misc.php?action=threadlog&edit=1&uid=" . $uid);
        }

        $per_page = intval($mybb->settings['threadlog_perpage']);

        $page = $mybb->get_input('page', MyBB::INPUT_INT);
        if ($page && $page > 0) {
            $start = ($page - 1) * $per_page;
        } else {
            $start = 0;
            $page = 1;
        }

        $page_total = 0;

        $query = $db->simple_select("threads", "COUNT(*) AS threads", "visible = 1" . $tids . $forum_select);
        $threadlog_total = $db->fetch_field($query, "threads");
        $count_total = $threadlog_total; // getting the total here, since it's convenient

        $multipage = multipage($threadlog_total, $per_page, $page, $threadlog_url);

        // get replies total
        $query = $db->simple_select("threads", "tid", "visible = 1 AND `closed` != 1 AND `lastposteruid` != " . $uid . $tids . $forum_select);
        $count_replies = $db->num_rows($query);

        // get active & closed total
        $query = $db->simple_select("threads", "tid", "visible = 1 AND `closed` = 1" . $tids . $forum_select);
        $count_closed = $db->num_rows($query);
        $count_active = $count_total - $count_closed;

        // final query
        $query = $db->simple_select("threads t
            left join " . $db->table_prefix . "threadlog l on l.ltid = t.tid and l.luid = " . $uid,
            "t.*, COALESCE(l.torder, 9999) as torder, l.description, l.thidden",
            "visible = 1" . $tids . $forum_select . " ORDER BY torder DESC, t.tid DESC LIMIT " . $start . ", " . $per_page);
        if ($db->num_rows($query) < 1) {
            eval("\$threadlog_list .= \"" . $templates->get("threadlog_nothreads") . "\";");
        }
        while ($thread = $db->fetch_array($query)) {

            $page_total++;

            $tid = $thread['tid'];

            $posts_query = $db->simple_select("posts", "tid", "visible = 1 AND tid = '" . $tid . "'");
            $thread_posts = $db->num_rows($posts_query);

            // set up row styles
            if ($page_total % 2) {
                $thread_row = "trow2";
            } else {
                $thread_row = "trow1";
            }

            // set up classes for active, needs reply, closed and hidden threads
            if ($thread['thidden'] & !$editing) {
                // if a thread is hidden, don't show it ever
                $thread_status = "thidden";

                // Also determine if it needs to be removed from counts
                $count_total--;
                if ($thread['closed'] == 1) {
                    $count_closed--;
                } else {
                    $count_active--;
                    if ($thread['lastposteruid'] != $uid) {
                        $count_replies--;
                    }
                }
            } else if ($thread['closed'] == 1) {
                $thread_status = "closed";
            } else {
                $thread_status = "active";

                if ($thread['lastposteruid'] != $uid) {
                    $thread_status .= " needs-reply";
                }
            }

            // set up thread link
            $thread_title = "<a href=\"{$mybb->settings['bburl']}/showthread.php?tid=" . $thread['tid'] . "\">" . $thread['subject'] . "</a>";

            // set up thread date
            $thread_date = date($mybb->settings['dateformat'], $thread['dateline']);

            // set up last poster
            $thread_latest_poster = "<a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=" . $thread['lastposteruid'] . "\">" . $thread['lastposter'] . "</a>";

            // set up date of last post
            $thread_latest_date = date($mybb->settings['dateformat'], $thread['lastpost']);

            // set up thread prefix
            $thread_prefix = '';
            $query2 = $db->simple_select("threadprefixes", "displaystyle", "pid = " . $thread['prefix']);
            $prefix = $db->fetch_array($query2);
            if ($thread['prefix'] != 0) {
                $thread_prefix = $prefix['displaystyle'];
            }

            // set up skills/attributes, but only if it exists!
            if ($db->table_exists('usernotes')) {
                $usernotes = '';
                $query5 = $db->simple_select("usernotes", "*", "tid = " . $thread['tid'] . " AND uid = " . $uid);
                $usernotes = $db->fetch_array($query5);
            }

            // set up participants
            $thread_participants = 'N/A';
            $i = 0;
            $query4 = $db->simple_select("posts", "DISTINCT uid, username", "tid = " . $thread['tid'] . " AND uid != '" . $uid . "'");
            while ($participant = $db->fetch_array($query4)) {
                $i++;
                if ($i == 1) {
                    $thread_participants = "<a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=" . $participant['uid'] . "\">" . $participant['username'] . "</a>";
                } else {
                    $thread_participants .= ", <a href=\"{$mybb->settings['bburl']}/member.php?action=profile&uid=" . $participant['uid'] . "\">" . $participant['username'] . "</a>";
                }
            }

            // Handle edit items for each row
            if ($editing) {
                eval('$threadlog_row_order = "' . $templates->get("threadlog_row_order") . '";');
                eval('$threadlog_description_input = "' . $templates->get("threadlog_description_input") . '";');
            }

            // add the row to the list
            eval("\$threadlog_list .= \"" . $templates->get("threadlog_row") . "\";");

        } // end while

        // Handle edit items for whole page
        if ($editing) {
            eval('$threadlog_order_header = "' . $templates->get("threadlog_order_header") . '";');
            eval('$threadlog_save_button = "' . $templates->get("threadlog_save_button") . '";');
        } else if ($uid == $mybb->user['uid']) {
            eval('$threadlog_edit_link = "' . $templates->get("threadlog_edit_link") . '";');
        }

        eval("\$threadlog_page = \"" . $templates->get("threadlog_page") . "\";");

        output_page($threadlog_page);

        exit;
    } // end threadlog action
}

function threadlog_post($uid)
{
    global $mybb, $db;


    if ($uid != $mybb->user['uid'] || $mybb->request_method != 'post') {
        return;
    }
    $torders = $mybb->input['torder'];
    $descriptions = $mybb->input['description'];
    $hiddens = $mybb->input['thidden'];

    foreach ($torders as $tid => $order) {
        $description = ($descriptions[$tid]);
        $hidden = ($hiddens[$tid]);
        $description = str_replace('"', "&quot;", $description);
        if (is_integer((int)$order) && strlen($description) <= 1000) {
            $query = $db->simple_select('threadlog', '*', 'ltid = ' . $tid . ' AND luid = ' . $uid);
            if ($query->num_rows) {
                $db->update_query('threadlog',
                    array('torder' => (int)$order,
                        'description' => $db->escape_string($description),
                        'thidden' => (bool)$hidden),
                    'ltid = ' . $tid . ' AND luid = ' . $uid);
            } else {
                $db->insert_query('threadlog',
                    array('ltid' => (int)$tid,
                        'luid' => (int)$uid,
                        'torder' => (int)$order,
                        'description' => $db->escape_string($description),
                        'thidden' => (bool)$hidden));
            }
        }
    }

    // Send user back to threadlog
    header("Location: misc.php?action=threadlog&uid" . $uid);
    die();
}

// add field to ACP

$plugins->add_hook("admin_forum_management_edit", "threadlog_forum_edit");
$plugins->add_hook("admin_forum_management_edit_commit", "threadlog_forum_commit");

function threadlog_forum_edit()
{
    global $plugins;
    $plugins->add_hook("admin_formcontainer_end", "threadlog_formcontainer_editform");
}

function threadlog_formcontainer_editform()
{
    global $mybb, $db, $lang, $form, $form_container, $fid;

    // print_r($mybb->input['threadlog_include']); die();

    $query = $db->simple_select('forums', 'threadlog_include', "fid='{$fid}'", array('limit' => 1));
    $include = $db->fetch_field($query, 'threadlog_include');

    if ($form_container->_title == "Edit Forum") {
        // create input fields
        $threadlog_forum_include = array(
            $form->generate_check_box("threadlog_include", 1, "Include in threadlog?", array("checked" => $include))
        );
        $form_container->output_row("Threadlog", "", "<div class=\"group_settings_bit\">" . implode("</div><div class=\"group_settings_bit\">", $threadlog_forum_include) . "</div>");
    }
}

function threadlog_forum_commit()
{
    global $db, $mybb, $cache, $fid;

    $update_array = array(
        "threadlog_include" => $mybb->get_input('threadlog_include', MyBB::INPUT_INT),
    );
    $db->update_query("forums", $update_array, "fid='{$fid}'");

    $cache->update_forums();
}
