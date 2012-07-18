<?php
/**
 * BrowserID plugin
 * 
 * PHP Version 5.3.10
 * 
 * @category Plugin
 * @package  MyBB
 * @author   Pierre Rudloff <contact@rudloff.pro>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License
 * @link     http://mybb.com/
 * */
 
/**
 * Return information about the plugin
 * 
 * @return array
 * */
function Browserid_info()
{
    return array(
        "name"		=> "BrowserID",
        "description"		=> "Login with Mozilla BrowserID (Persona)",
        "author"		=> "Pierre Rudloff",
        "authorsite"    => "http://rudloff.pro",
        "version"		=> "0.1",
        "guid" 			=> "",
        "compatibility"	=> "*"
        );
}

$plugins->add_hook("pre_output_page", "browserid_button");
if (isset($_GET["assertion"])) {
    $plugins->add_hook("member_login", "browserid_login");
}

/**
 * Display a BrowserID button
 * 
 * @param string $page Page
 * 
 * @return string
 * */
function Browserid_button($page)
{
    $page = str_replace(
        "</a>)</span>".PHP_EOL."<!-- end: header_welcomeblock_guest -->",
        "</a> &mdash; <a id='browserid' href='member.php?action=login'>".
        "BrowserID</a>)</span>".PHP_EOL.
        "<!-- end: header_welcomeblock_guest -->",
        $page
    );
    $page=str_replace(
        "</head>",
        "<script src='https://browserid.org/include.js'></script>
        <script>
        var initLogin = function () {
            'use strict';
            var login, connect, loginBtn;
            login = function (assertion) {
                if (assertion) {
                    document.location = 'member.php?action=login&assertion=' + assertion;
                }
            };
            connect = function (e) {
                e.preventDefault();
                navigator.id.get(login);
                return false;
            };
            loginBtn = document.getElementById('browserid');
            if (loginBtn.addEventListener) {
                loginBtn.addEventListener('click', connect, true);
            } else if (loginBtn.attachEvent) {
                loginBtn.attachEvent('onclick', connect);
            }
        };
        if (window.addEventListener) {
            window.addEventListener('load', initLogin, false);
        } else if (window.attachEvent) {
            window.attachEvent('onload', initLogin);
        }

        </script>
        </head>",
        $page
    );
    return $page;
}

/**
 * Login with BrowserID
 * 
 * @return void
 * */
function Browserid_login()
{
    $curl = curl_init("https://browserid.org/verify");
    if (isset($_GET["assertion"])) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt(
            $curl, CURLOPT_POSTFIELDS, "assertion=".strval(
                $_GET["assertion"]
            )."&audience=".$_SERVER["HTTP_HOST"]
        );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response=json_decode(strval(curl_exec($curl)));
        curl_close($curl);
         
        if ($response->status==="okay") {
            if (username_exists($response->email)) {
                global $db, $plugins, $session;
                $user = $db->fetch_array(
                    $db->simple_select(
                        "users", "uid, loginkey",
                        "LOWER(email)='".$response->email."'",
                        array('limit' => 1)
                    )
                );
                my_setcookie('loginattempts', 1);
                $db->delete_query(
                    "sessions", "ip='".$db->escape_string($session->ipaddress).
                    "' AND sid != '".$session->sid."'"
                );
                $newsession = array(
                    "uid" => $user['uid'],
                );
                $db->update_query(
                    "sessions", $newsession, "sid='".$session->sid."'"
                );
                
                $db->update_query(
                    "users", array("loginattempts" => 1), "uid='{$user['uid']}'"
                );
                
                my_setcookie(
                    "mybbuser", $user['uid']."_".$user['loginkey'], null, true
                );
                my_setcookie("sid", $session->sid, -1, true);
                $plugins->run_hooks("member_do_login_end");
                redirect("index.php", $lang->redirect_loggedin);
            } else {
                global $lang;
                error($lang->error_nomember);
            }
        } else {
            error($response->reason);
        }
    }
}

?>
