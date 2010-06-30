<?php

if (is_file(dirname(__FILE__) .'/../../../VERSION')) {
  $fd = fopen (dirname(__FILE__) .'/../../../VERSION', "r");
  while ($line = fscanf ($fd, "%[a-zA-Z0-9,. ]=%[a-zA-Z0-9,. ]")) {
    list ($key, $val) = $line;
    if ($key == "VERSION")
      $version = $val . "-";
  }
  fclose($fd);
} else {
  $version = "dev";
}

define("VERSION",$version.'dev');

require_once dirname(__FILE__)."/commonlib/lib/userlib.php";
include_once dirname(__FILE__)."/commonlib/lib/maillib.php";

# set some variables
if (!isset ($_GET["pi"]))
  $_GET["pi"] = "";

# make sure magic quotes are on. Try to switch it on, this may fail
ini_set("magic_quotes_gpc","on");

$GLOBALS["img_tick"] = '<img src="images/tick.gif" alt="Yes" />';
$GLOBALS["img_cross"] = '<img src="images/cross.gif" alt="No" />';

# if keys need expanding with 0-s
$checkboxgroup_storesize = 1; # this will allow 10000 options for checkboxes

# identify pages that can be run on commandline
$commandline_pages = array('send','processqueueforked','processqueue','processbounces','import','upgrade','convertstats','reindex'); // ,'getrss' //Obsolete by rssmanager plugin

if (isset($message_envelope))
  $envelope = "-f$message_envelope";

$database_schema = '';
$database_connection = Sql_Connect($database_host,$database_user,$database_password,$database_name);
Sql_Set_Search_Path($database_schema);

if (!empty($GLOBALS["SessionTableName"])) {
  include_once dirname(__FILE__)."/sessionlib.php";
}

if (!isset($table_prefix)) {
  $table_prefix = "";
}
if (!isset($usertable_prefix)) {
  $usertable_prefix = $table_prefix;
}

include_once dirname(__FILE__)."/pluginlib.php";
include_once dirname(__FILE__)."/structure.php";

$tables = array();
foreach ($GLOBALS["DBstructuser"] as $tablename => $tablecolumns) {
  $tables[$tablename] =  $usertable_prefix . $tablename;
};
foreach ($GLOBALS["DBstructphplist"] as $tablename => $tablecolumns) {
  $tables[$tablename] =  $table_prefix . $tablename;
};
# unset the struct arrays, DBStruct and tables globals remain for the rest of the program
unset($GLOBALS["DBstructuser"]);
unset($GLOBALS["DBstructphplist"]);

$commandlinePlugins = array();
if (sizeof($GLOBALS["plugins"])) {
  foreach ($GLOBALS["plugins"] as $pluginName => $plugin) {
    $commandlinePlugins = array_merge($commandlinePlugins, $plugin->commandlinePlugins);
  }
}

$domain = getConfig("domain");
$website = getConfig("website");
$xormask = getConfig('xormask');
if (!$xormask) {
  $xormask = md5(uniqid(rand(), true));
  SaveConfig("xormask",$xormask,0,1);
}
define('XORmask',$xormask);

//obsolete, moved to rssmanager plugin
//$GLOBALS['rssfrequencies'] = array(
//#  "hourly" => $strHourly, # to be added at some other point
//  "daily" => $strDaily,
//  "weekly" => $strWeekly,
//  "monthly" => $strMonthly
//);

$redfont = "";
$efont = "";
$GLOBALS["coderoot"] = dirname(__FILE__).'/';
$GLOBALS["mail_error"] = "";
$GLOBALS["mail_error_count"] = 0;

function SaveConfig($item,$value,$editable=1,$ignore_errors = 0) {
  global $tables;
  ## in case DB hasn't been initialised
  if (empty($_SESSION['hasconf'])) {
    $_SESSION['hasconf'] = Sql_Table_Exists($tables["config"]);
  } 
  if (empty($_SESSION['hasconf'])) return;
  if ($value == "false" || $value == "no") {
    $value = 0;
  } else
    if ($value == "true" || $value == "yes") {
    $value = 1;
  }
  ## force reloading config values in session
  unset($_SESSION['config']);
  return Sql_Replace( $tables["config"], array('item'=>$item, 'value'=>$value, 'editable'=>$editable), 'item');
}

/*
  We request you retain the $PoweredBy variable including the links.
  This not only gives respect to the large amount of time given freely
  by the developers  but also helps build interest, traffic and use of
  PHPlist, which is beneficial to it's future development.

  You can configure your PoweredBy options in your config file

  Michiel Dethmers, Tincan Ltd 2001,2004
*/
if (ereg("dev", VERSION))
  $v = "dev";
else
  $v = VERSION;
if (REGISTER) {
  $PoweredByImage = '<p class="poweredby"><a href="http://www.phplist.com"><img src="http://phplist.tincan.co.uk/images/'.$v.'/power-phplist.png" width=70 height=30 title="Powered by PHPlist version '.$v.', &copy; tincan ltd" alt="Powered by PHPlist'.$v.', &copy tincan ltd" border="0"/></a></p>';
} else {
  $PoweredByImage = '<p class="poweredby"><a href="http://www.phplist.com"><img src="images/power-phplist.png" width=70 height=30 title="Powered by PHPlist version '.$v.', &copy; tincan ltd" alt="Powered by PHPlist'.$v.', &copy tincan ltd" border="0"/></a></p>';
}
$PoweredByText = '<span class="poweredphplist">powered by <a href="http://www.phplist.com" class="poweredphplist" target="_blank">phplist</a> v '.$v.', &copy; <a href="http://tincan.co.uk/powered" target="_blank" class="poweredphplist">tincan ltd</a></span>';
if (!TEST && REGISTER) {
  if (!PAGETEXTCREDITS) {
    ;
    $PoweredBy = $PoweredByImage;
  } else {
    $PoweredBy = $PoweredByText;
  }
} else {
  if (!PAGETEXTCREDITS) {
    ;
    $PoweredBy = $PoweredByImage;
  } else {
    $PoweredBy = $PoweredByText;
  }
}
# some other configuration variables, which need less tweaking
# number of users to show per page if there are more
define ("MAX_USER_PP",50);
define("MAX_MSG_PP",5);

function formStart($additional="") {
  global $form_action,$page,$p;
  # depending on server software we can post to the directory, or need to pass on the page
  if ($form_action) {
    $html = sprintf('<form method="post" action="%s" %s>',$form_action,$additional);
    # retain all get variables as hidden ones
    foreach (array (
        "p",
        "page"
        ) as $key) {
      $val = $_REQUEST[$key];
      if ($val)
  $html .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $val);
    }
  } else
    $html = sprintf('<form method="post" action="" %s>',$additional);
/*    $html = sprintf('<form method=post action="./" %s>
    %s',$additional,isset($page) ?
    '<input type="hidden" name="page" value="'.$page.'" />':(
    isset($p)?'<input type="hidden" name="p" value="'.$p.'" />':"")
    );
*/
  return $html;
}

function checkAccess($page) {
  global $tables;
  if (!$GLOBALS["require_login"] || isSuperUser())
    return 1;
  # check whether it Is a page to protect
  $query = sprintf("select id from %s where page = ?", $tables['task']);
  $rs = Sql_Query_Params($query, array($page));
  if (!Sql_Num_Rows( $rs ))
    {
    return 1;
    }
  $query
  = ' select level'
  . ' from %s t, %s at'
  . ' where at.taskid = t.id'
  . '   and adminid = ?'
  . '   and t.page = ?';
  $query = sprintf($query, $tables['task'], $tables['admin_task']);
  $req = Sql_Query_Params($query, array($_SESSION["logindetails"]["id"], $page));
  $row = Sql_Fetch_Row($req);
  if (!$row[0])
    return 0;
  return 1;
}

function sendReport($subject,$message) {
  $report_addresses = explode(",",getConfig("report_address"));
  foreach ($report_addresses as $address) {
    sendMail($address,$GLOBALS["installation_name"]." ".$subject,$message);
   }
}

function sendMessageStats($msgid) {
  global $stats_collection_address,$tables;
  $msg = '';
  if (defined("NOSTATSCOLLECTION") && NOSTATSCOLLECTION) {
    return;
   }
  if (!isset($stats_collection_address)) {
    $stats_collection_address = 'phplist-stats@tincan.co.uk';
  }
  $data = Sql_Fetch_Array_Query(sprintf('select * from %s where id = %d', $tables["message"], $msgid));
  $msg .= "PHPlist version ".VERSION . "\n";
  $diff = timeDiff($data["sendstart"],$data["sent"]);

  if ($data["id"] && $data["processed"] > 10 && $diff != "very little time") {
    $msg .= "\n".'Time taken: '.$diff;
    foreach (array (
        'entered',
        'processed',
        'sendstart',
        'sent',
        'htmlformatted',
        'sendformat',
        'template',
        'astext',
        'ashtml',
        'astextandhtml',
        'aspdf',
        'astextandpdf'
      ) as $item) {
        $msg .= "\n".$item.' => '.$data[$item];
    }
    if ($stats_collection_address == 'phplist-stats@tincan.co.uk' && $data["processed"] > 500) {
      mail($stats_collection_address,"PHPlist stats",$msg);
    } else {
      mail($stats_collection_address,"PHPlist stats",$msg);
    }
  }
}

function normalize($var) {
  $var = str_replace(" ","_",$var);
  $var = str_replace(";","",$var);
  return $var;
}

function ClineSignature() {
  return "PHPlist version ".VERSION." (c) 2000-".date("Y")." Tincan Ltd, http://www.phplist.com\n";
}

function ClineError($msg) {
  ob_end_clean();
  print ClineSignature();
  print "\nError: $msg\n";
  exit;
}

function clineUsage($line = "") {
#  if (!ereg("dev",VERSION))
    ob_end_clean();
  print clineSignature();
  print "Usage: ".$_SERVER["SCRIPT_FILENAME"]." -p page $line\n\n";
  exit;
}

function Error($msg) {
  if ($GLOBALS["commandline"]) {
    clineError($msg);
    return;
  }
  print '<div class="error" align="center">'.$GLOBALS["I18N"]->get("error").": $msg </div>";

  $GLOBALS["mail_error"] .= 'Error: '.$msg."\n";
  $GLOBALS["mail_error_count"]++;
  if (is_array($_POST) && sizeof($_POST)) {
    $GLOBALS["mail_error"] .= "\nPost vars:\n";
    while (list($key,$val) = each ($_POST)) {
      if ($key != "password")
        $GLOBALS["mail_error"] .= $key . "=" . $val . "\n";
      else
        $GLOBALS["mail_error"] .= "password=********\n";
    }
  }
}

function clean ($value) {
  $value = trim($value);
  $value = ereg_replace("\r","",$value);
  $value = ereg_replace("\n","",$value);
  $value = ereg_replace('"',"&quot;",$value);
  $value = ereg_replace("'","&rsquo;",$value);
  $value = ereg_replace("`","&lsquo;",$value);
  $value = stripslashes($value);
  return $value;
}

function join_clean($sep,$array) {
  # join values without leaving a , at the end
  $arr2 = array();
  foreach ($array as $key => $val) {
    if ($val) {
      $arr2[$key] = $val;
    }
  }
  return join($sep,$arr2);
}

function Fatal_Error($msg) {
  if ($GLOBALS['commandline']) {
    @ob_end_clean();
    print "\n".$GLOBALS["I18N"]->get("fatalerror").": ".strip_tags($msg)."\n";
    @ob_start();
  } else {
    if (isset($GLOBALS['I18N']) && is_object($GLOBALS['I18N'])) {
      print '<div align="center" class="error">'.$GLOBALS["I18N"]->get("fatalerror").": $msg </div>";
    } else {
      print '<div align="center" class="error">'."Fatal Error: $msg </div>";
    }
    $message = '

    An error has occurred in the Mailinglist System
    URL: '.$_SERVER["REQUEST_URI"].'
    Error: ' . $msg;
    if (function_exists("sendMail")) {
      sendMail(getConfig("report_address"),"Mail list error",$message,"");
    }
  }
 # include "footer.inc";
 # exit;
  return 0;
}

function Warn($msg) {
  if ($GLOBALS['commandline']) {
    @ob_end_clean();
    print "\n".strip_tags($GLOBALS["I18N"]->get("warning").": ".$msg)."\n";
    @ob_start();
  } else {
    print '<div align=center class="error">'.$GLOBALS["I18N"]->get("warning").": $msg </div>";
    $message = '

    An warning has occurred in the Mailinglist System

    ' . $msg;
  }
#  sendMail(getConfig("report_address"),"Mail list warning",$message,"");
}

function Info($msg) {
  if ($GLOBALS['commandline']) {
    @ob_end_clean();
    print "\n".strip_tags($GLOBALS["I18N"]->get("information").": ".$msg)."\n";
    @ob_start();
  } else {
    print '<div class="info">'.$GLOBALS["I18N"]->get("information").": $msg </div>";
  }
}

function pageTitle($page) {
  $page_title = '';
  include dirname(__FILE__).'/lan/'.$_SESSION['adminlanguage']['iso'].'/pagetitles.php';
  if (!empty($page_title)) {
    $title = $page_title;
  } else {
    $title = $page;
  }
  return $title;
}

$GLOBALS['pagecategories'] = array(
  ## category title => array( 
    # toplink => page to link top menu to
    # pages => pages in this category
    
  'subscribers' => array(
     'toplink' => 'usermgt',
     'pages' => array(
        'users',
        'usermgt',
        'members',
        'import',
        'import1',
        'import2',
        'import3',
        'import4',
        'importsimple',
        'dlusers',
        'export',
        'listbounces',
        'massremove',
        'massunconfirm',
        'reconcileusers',
        'usercheck',
        'userhistory',
        'user',
      ),
     'menulinks' => array(
        'users',
        'usermgt',
        'import',
        'export',
        'listbounces',
        'massremove',
        'massunconfirm',
        'reconcileusers',
        'usercheck',
      ),
      
   ),
  'campaigns' => array(
      'toplink' => 'campaignmgt',
      'pages' => array(
        'send',
        'sendprepared',
        'message',
        'messages',
        'viewmessage',
        'templates',
        'template',
        'viewtemplate',
        'bouncemgt',
      ),
      'menulinks' => array(
        'send',
        'messages',
        'templates',
        'bouncemgt',
      ),
  ),
  'statistics' => array(
      'toplink' => 'statsmgt',
      'pages' => array(
        'mviews',
        'mclicks',
        'uclicks',
        'userclicks',
        'statsmgt',
        'statsoverview',
        'domainstats'
      ),
      'menulinks' => array(
        'statsoverview',
        'mviews',
        'mclicks',
        'uclicks',
        'domainstats'
      ),
  ),
  'system' => array(
      'toplink' => 'system',
      'pages' => array(
        'bounce',
        'bounces',
        'convertstats',
        'dbcheck',
        'eventlog',
        'generatebouncerules',
        'initialise',
        'upgrade',
        'processqueue',
        'processbounces',
        'reindex',
        'resetstats',
      ),
      'menulinks' => array(
        'bounces',
        'dbcheck',
        'eventlog',
        'generatebouncerules',
        'initialise',
        'upgrade',
        'processqueue',
        'processbounces',
        'reindex',
      ),
  ),
  'develop' => array(
      'toplink' => 'develop',
      'pages' => array(
        'checki18n',
        'stresstest',
        'subscriberstats',
        'tests',
        'resetstats',
      ),
      'menulinks' => array(
        'checki18n',
        'stresstest',
        'subscriberstats',
        'tests',
        'resetstats',
      ),
  ),
  'config' => array(
      'toplink' => 'setup',
      'pages' => array(
        'setup',
        'configure',
        'list',
        'editlist',
        'catlists',
        'spage',
        'spageedit',
        'admins',
        'admin',
        'importadmin',
        'adminattributes',
        'attributes',
        'editattributes',
        'defaults',
        'bouncerules',
        'bouncerule',
        'checkbouncerules',
      ),
      'menulinks' => array(
        'setup',
        'configure',
        'list',
        'attributes',
        'editattributes',
        'spage',
        'admins',
        'importadmin',
        'adminattributes',
        'bouncerules',
        'checkbouncerules',
        'catlists',
      ),
  ),
  'info' => array(
      'toplink' => 'about',
      'pages' => array(
        'about',
        'community',
        'home',
        'vote'
      ),
      'menulinks' => array(
        'about',
        'community',
        'home',
      ),
  ),
  'plugins' => array(
    'toplink' => 'plugins',
    'pages' => array(),
    'menulinks' => array(),
  ),
);

function pageCategory($page) {
  foreach ($GLOBALS['pagecategories'] as $category => $cat_details) {
    if (in_array($page,$cat_details['pages'])) {
      return $category;
    }
  }
  return '';
}

$main_menu = array(
  "configure" => "Configure",
  "community" => "Help",
  "about" => "About",
  "div1" => "<hr />",
  "list" => "Lists",
  "send"=>"Send a message",
  "users" => "Users",
  "usermgt" => "Manage Users",
  "spage" => "Subscribe Pages",
  "messages" => "Messages",
  'statsmgt' => 'Statistics',
  "div2" => "<hr />",
  "templates" => "Templates",
  "preparesend"=>"Prepare a message",
  "sendprepared"=>"Send a prepared message",
  "processqueue"=>"Process Queue",
  "processbounces"=>"Process Bounces",
  "bouncemgt" => 'Manage Bounces',
  "bounces"=>"View Bounces",
  "eventlog"=>"Eventlog"
);

function newMenu() {
  if (isset ($GLOBALS["firsttime"])) {
    return;
  }
  if (!CLICKTRACK) {
    unset($GLOBALS["main_menu"]['statsmgt']);
  }
  $shade = 1;
  $spb = '<li class="shade0">';
#  $spb = '<li class="shade2">';
  $spe = '</li>';
  $nm = strtolower(NAME);
  if ($nm != "phplist") {
    $GLOBALS["main_menu"]["community"] = "";
  }
  if (USE_ADVANCED_BOUNCEHANDLING) {
    $GLOBALS["main_menu"]["bounces"] = "";
    $GLOBALS["main_menu"]["processbounces"] = "";
  } else {
    $GLOBALS["main_menu"]["bouncemgt"] = '';
  }

  if ($GLOBALS["require_login"] && (!isset ($_SESSION["adminloggedin"]) || !$_SESSION["adminloggedin"]))
    return $spb . PageLink2('home', $GLOBALS["I18N"]->get('Main Page')) . '<br />' . $spe . $spb . PageLink2('about', $GLOBALS["I18N"]->get('about') . ' phplist') . '<br />' . $spe;

  $access = accessLevel("spage");
  switch ($access) {
    case "owner":
      $subselect = sprintf(' where owner = %d', $_SESSION["logindetails"]["id"]);
      break;
    case "all":
    case "view":
      $subselect = "";
      break;
    case "none":
    default:
      $subselect = " where id = 0";
      break;
  }
  if (TEST && REGISTER)
    $pixel = '<img src="http://phplist.tincan.co.uk/images/pixel.gif" width="1" height="1" alt="" />';
  else
    $pixel = "";
  global $tables;
  $html = "";
/*   if ($GLOBALS["require_login"]) */
/*     $html .= $spb.PageLink2("logout",$GLOBALS["I18N"]->get("logout")).'<br />'.$spe; */

  $html .= $spb.PageLink2("home",$GLOBALS["I18N"]->get("Main Page"),'',true).$spe;

  /*
  $req = Sql_Query(sprintf('select * from %s %s',$tables["subscribepage"],$subselect));
  $spages = array();
  if (Sql_Affected_Rows()) {
    $spages["div1"] = $GLOBALS["strSubscribeTitle"];
    while ($row = Sql_Fetch_Array($req)) {
      $spages[sprintf('%s&amp;id=%d',getConfig("subscribeurl"),$row["id"])] = $row["title"];
    }
    $url = getConfig("unsubscribeurl");
    if ($url)
      $spages[$url] = 'Unsubscribe';
  } else {
#    $html .= $spb.sprintf('<a href="%s">%s</a>',getConfig("subscribeurl"),$GLOBALS["strSubscribeTitle"]).$spe;
    $spages["spage"] = "Create Subscribe Pages";
  }
  if ($tables["attribute"] && Sql_Table_Exists($tables["attribute"])) {
    $attrmenu = array();
    $res = Sql_Query("select * from {$tables['attribute']}",1);
    while ($row = Sql_Fetch_array($res)) {
      if ($row["type"] != "checkbox" && $row["type"] != "textarea" && $row["type"] != "textline" && $row["type"] != "hidden")
        $attrmenu["editattributes&amp;id=".$row["id"]] = strip_tags($row["name"]);
    }
  }
  */

  if (isset($_GET['page'])) {
    $thispage = $_GET['page'];
  } else {
    $thispage = 'home';
  }
  $thispage_category = pageCategory($thispage);
  
  if (empty($thispage_category) && empty($_GET['pi'])) {
    $thispage_category = '';
  } elseif (!empty($_GET['pi'])) {
    $thispage_category = 'plugins';
  }

  $GLOBALS['main_menu'] = array(
    "community" => 'Help',
    "about" =>  'about',
    "logout" => "logout",
    "div1" => "<hr />",
    'category' => $thispage_category,
  );
  if (!empty($thispage_category)) {
    foreach ($GLOBALS['pagecategories'][$thispage_category]['menulinks'] as $category_page) {
      $GLOBALS['main_menu'][$category_page] = $category_page;
    }
  }

  foreach ($GLOBALS["main_menu"] as $page => $desc) {
    if (!$desc) continue;
    $link = PageLink2($page,$GLOBALS["I18N"]->get($desc));
    if ($link) {
      if ($page == "preparesend" || $page == "sendprepared") {
        if (USE_PREPARE) {
          $html .= $spb.$link.$spe;
        }
      } 
      // don't use the link for a rule
      elseif ($desc == "<hr />") {
        $html .= '<li>'.$desc.'</li>';
      } elseif ($page == 'category') {
      #  $html .= '<li><h3>'.$GLOBALS['I18N']->get($thispage_category).'</h3></li>';
        $html .= '<li><h3>'.$GLOBALS['I18N']->get('In this section').'</h3></li>';
      } else {
        $html .= $spb.$link.$spe;
      }
    }
  }
/*
  if (sizeof($GLOBALS["plugins"])) {
    $html .= $spb."<hr/>".$spe;
    foreach ($GLOBALS["plugins"] as $pluginName => $plugin) {
      $html .= $spb.PageLink2("main&amp;pi=$pluginName",$pluginName).$spe;
    }
  } 
*/

  return '<ul class="contextmenu">'.$html.'</ul>' . $pixel;
}

function recentlyVisited() {
  $html = '';
  if (isset($_SESSION['browsetrail']) && is_array($_SESSION['browsetrail'])) {
    $shade = 0;
    $html .= '<h3>'.$GLOBALS['I18N']->get('Recently Visited').'</h3><ul class="recentlyvisited">';
    $browsetrail = array_unique($_SESSION['browsetrail']);
    $browsetrail = array_reverse($browsetrail);
    foreach ($browsetrail as $pageid => $visitedpage) {
      if (strpos($visitedpage,'SEP')) {
        list($pageurl,$pagetitle) = explode('SEP',$visitedpage);
        if ($pagetitle != 'phplist') {  ## pages with no title
          $pagetitle = str_replace('%',' ',$pagetitle);
          if (strpos($pagetitle,' ') > 20) $pagetitle = substr($pagetitle,0,10).' ...';
          $html .= '<li class="shade'.$shade.'"><a href="./'.$pageurl.'" title="'.htmlspecialchars($pagetitle).'"><!--'.$pageid.'-->'.$pagetitle.'</a></li>';
          $shade = !$shade;
        }
      }
    }
    $html .= '</ul>';
  }
  $_SESSION['browsetrail'] = array_slice($_SESSION['browsetrail'],0,6);
  return $html;
}


function topMenu() {
  if (empty($_SESSION["logindetails"])) return '';
  
  if ($_SESSION["logindetails"]['superuser']) {
    if (sizeof($GLOBALS["plugins"])) {
      foreach ($GLOBALS["plugins"] as $pluginName => $plugin) {
        array_push($GLOBALS['pagecategories']['plugins']['menulinks'],'main&pi='.$pluginName);
        $menulinks = $plugin->menuLinks;
        foreach ($menulinks as $link => $linkDetails) {
          if (isset($GLOBALS['pagecategories'][$linkDetails['category']])) {
            array_push($GLOBALS['pagecategories'][$linkDetails['category']]['menulinks'],$link.'&pi='.$pluginName);
          }
        }
#          PageLink2("main&amp;pi=$pluginName",$pluginName).$spe;
      }
    } 
  }
  
  $topmenu = '';
  $topmenu .= '<div id="menuTop">';
  foreach ($GLOBALS['pagecategories'] as $category => $categoryDetails) {
    if (
      $category == 'hide' ||
      ($category == 'develop' && empty($GLOBALS['developer_email']))) 
      continue;
    
    $thismenu = '';
    foreach ($categoryDetails['menulinks'] as $page) {
      $title = $GLOBALS['I18N']->pageTitle($page);
      
      $link = PageLink2($page,$title,'',true);
      if ($link) {
        $thismenu .= '<li>'.$link.'</li>';
      }
    }
    if (!empty($categoryDetails['toplink'])) {
      $categoryurl = PageUrl2($categoryDetails['toplink'],'',true);
      if ($categoryurl) {
        $topmenu .=  '<ul><li><h3><a href="'.$categoryurl.'">'.$GLOBALS['I18N']->get($category).'</a></h3><ul>'.$thismenu.'</ul></li></ul>';
      } else {
        $topmenu .=  '<ul><li><h3><span>'.$GLOBALS['I18N']->get($category).$categoryurl.'</span></h3><ul>'.$thismenu.'</ul></li></ul>';
      }
    }
  }

  $topmenu .=  '</div>';

  return $topmenu;
}

function PageLink2($name,$desc="",$url="",$no_plugin = false) {
  if ($url)
    $url = "&amp;".$url;
  $access = accessLevel($name);
  $name = str_replace('&amp;','&',$name);
  $name = str_replace('&','&amp;',$name);
  
  if (empty($desc)) {
    $desc = $name;
  }
  
  if ($access == "owner" || $access == "all" || $access == "view") {
    if ($name == "processqueue" && !MANUALLY_PROCESS_QUEUE)
      return "";#'<!-- '.$desc.'-->';
    elseif ($name == "processbounces" && !MANUALLY_PROCESS_BOUNCES) return ""; #'<!-- '.$desc.'-->';
    else {
      if (!$no_plugin && !preg_match("/&amp;pi=/i",$name) && isset($_GET["pi"]) && isset($GLOBALS["plugins"][$_GET["pi"]]) && is_object($GLOBALS["plugins"][$_GET["pi"]])) {
        $pi = '&amp;pi='.$_GET["pi"];
      } else {
        $pi = "";
      }
      return sprintf('<a href="./?page=%s%s%s" title="%s">%s</a>',$name,$url,$pi,$desc,strtolower($desc));
    }
  } else
    return "";
#    return "\n<!--$name disabled $access -->\n";
#    return "\n$name disabled $access\n";
}

function PageLinkDialog ($name,$desc="",$url="") {
  ## as PageLink2, but add the option to ajax it in a popover window
  $link = PageLink2($name,$desc,$url);
  if ($link) {
    $link = str_replace('<a ','<div><a class="opendialog"',$link);
    $link .= '</div>';
  }
  return $link;
}

function PageLinkAjax ($name,$desc="",$url="") {
  ## as PageLink2, but add the option to ajax it in a popover window
  $link = PageLink2($name,$desc,$url);
  if ($link) {
    $link = str_replace('<a ','<div><a class="ajaxable"',$link);
    $link .= '</div>';
  }
  return $link;
}


function SidebarLink($name,$desc,$url="") {
  if ($url)
    $url = "&".$url;
  $access = accessLevel($name);
  if ($access == "owner" || $access == "all") {
    if ($name == "processqueue" && !MANUALLY_PROCESS_QUEUE)
      return '<!-- '.$desc.'-->';
    elseif ($name == "processbounces" && !MANUALLY_PROCESS_BOUNCES) return '<!-- ' . $desc . '-->';
    else
      return sprintf('<a href="./?page=%s%s" target="phplistwindow">%s</a>',$name,$url,strtolower($desc));
  } else
    return "\n<!--$name disabled $access -->\n";
#    return "\n$name disabled $access\n";
}

function PageURL2($name,$desc = "",$url="") {
  if (empty($name)) return '';
  if ($url)
    $url = "&amp;".$url;
  $access = accessLevel($name);
  if ($access == "owner" || $access == "all" || $access == "view") {
    if (!preg_match("/&amp;pi=/i",$name) && $_GET["pi"] && is_object($GLOBALS["plugins"][$_GET["pi"]])) {
      $pi = '&amp;pi='.$_GET["pi"];
    } else {
      $pi = "";
    }
    return sprintf('./?page=%s%s%s',$name,$url,$pi);
  } else {
    return '';
  }
}

#function ListofLists($messagedata,$fieldname,$subselect) {
function ListofLists($current,$fieldname,$subselect) {
  $categoryhtml = array();
  $categoryhtml['selected'] = '';
  $categoryhtml['all'] = '
  <li><input type="checkbox" name="'.$fieldname.'[all]"';
  if (!empty($current["all"])) {
    $categoryhtml['all'] .= "checked";
  }
  $categoryhtml['all'].= ' />'.$GLOBALS['I18N']->get('alllists').'</li>';

  $categoryhtml['all'] .= '<li><input type="checkbox" name="'.$fieldname.'[allactive]"';
  if (!empty($current["allactive"])) {
    $categoryhtml['all'] .= 'checked="checked"';
  }
  $categoryhtml['all'] .= ' />'.$GLOBALS['I18N']->get('All Active Lists').'</li>';

  $result = Sql_query('SELECT * FROM '.$GLOBALS['tables']['list']. $subselect.' order by category, name');
  while ($list = Sql_fetch_array($result)) {
    if (empty($list['category'])) {
      $list['category'] = $GLOBALS['I18N']->get('Uncategorised');
    }
    if (!isset($categoryhtml[$list['category']])) {
      $categoryhtml[$list['category']] = '';
    }
    if (isset($current[$list["id"]]) && $current[$list["id"]]) {
      $list['category'] = 'selected';
    }
    $categoryhtml[$list['category']] .= sprintf('<li><input type=checkbox name="'.$fieldname.'[%d]" value="%d" ',$list["id"],$list["id"]);
    # check whether this message has been marked to send to a list (when editing)
    if (isset($current[$list["id"]]) && $current[$list["id"]]) {
      $categoryhtml[$list['category']] .= "checked";
    }
    $categoryhtml[$list['category']] .= " />".stripslashes($list["name"]);
    if ($list["active"]) {
      $categoryhtml[$list['category']] .= ' (<span class="activelist">'.$GLOBALS['I18N']->get('listactive').'</span>)';
    } else {
      $categoryhtml[$list['category']] .= ' (<span class="inactivelist">'.$GLOBALS['I18N']->get('listnotactive').'</span>)';
    }

    if (!empty($list["description"])) {
      $desc = nl2br(stripslashes($list["description"]));
      $categoryhtml[$list['category']] .= "<br />$desc";
    }
    $categoryhtml[$list['category']] .= "</li>";
    $some = 1;
  }
  if (empty($categoryhtml['selected'])) unset($categoryhtml['selected']);
  return $categoryhtml;
}

function listSelectHTML ($current,$fieldname,$subselect) {
  $categoryhtml = ListofLists($current,$fieldname,$subselect);
  $tabno = 1;
  $listindex = $listhtml = '';
  foreach ($categoryhtml as $category => $content) {
    $listindex .= sprintf('<li><a href="#%s%d">%s</a></li>',$fieldname,$tabno,$category);
    $listhtml .= sprintf('<div id="%s%d"><ul>%s</ul></div>',$fieldname,$tabno,$content);
    $tabno++;
    $some = 1;
  }

  $html = '<div class="tabbed"><ul>'.$listindex.'</ul>';
  $html .= $listhtml;
  $html .= '</div>'; ## close tabbed

  if (!$some) {
    $html = $GLOBALS['I18N']->get('nolistsavailable');
  }
  return $html;
}

function getSelectedLists($fieldname) {
  if (in_array('all',array_keys($_POST[$fieldname]))) {
    ## load all lists
    $_POST[$fieldname] = array();
    $req = Sql_Query(sprintf('select id from %s',$GLOBALS['tables']['list']));
    while ($row = Sql_Fetch_Row($req)) {
      $_POST[$fieldname][$row[0]] = $row[0];
    }
  } elseif (in_array('allactive',array_keys($_POST[$fieldname]))) {
    ## load all active lists
    $_POST[$fieldname] = array();
    $req = Sql_Query(sprintf('select id from %s where active',$GLOBALS['tables']['list']));
    while ($row = Sql_Fetch_Row($req)) {
      $_POST[$fieldname][$row[0]] = $row[0];
    }
  }
  return $_POST[$fieldname];
}

function Redirect($page) {
  if (!empty($_SERVER['HTTP_HOST'])) {
    $website = $_SERVER['HTTP_HOST'];
  } else {
    ## could check SERVER_NAME as well
    $website = getConfig("website");
  }
  Header("Location: ".$GLOBALS['scheme']."://".$website.$GLOBALS["adminpages"]."/?page=$page");
  exit;
}

function formatBytes ($value) {
  $gb = 1024 * 1024 * 1024;
  $mb = 1024 * 1024;
  $kb = 1024;
  $gbs = $value / $gb;
  if ($gbs > 1)
    return sprintf('%2.2fGb',$gbs);
  $mbs = $value / $mb;
  if ($mbs > 1)
    return sprintf('%2.2fMb',$mbs);
  $kbs = $value / $kb;
  if ($kbs > 1)
    return sprintf('%dKb',$kbs);
  else
    return sprintf('%dBytes',$value);
}

# I would prefer not to use this version, as it is very heavy on memory, loading the content
# of an entire table in memory, which tends to fail, but the other one (below) is not
# always available, hmm
function upgradeTableOld($table,$tablestructure) {
  $columns = array();
  $records = array();

  $cols = Sql_Query("show columns from $table");
  while ($row = Sql_Fetch_Row($cols))
    array_push($columns,$row[0]);

  $recs = Sql_Query("select * from $table");
  while ($data = Sql_Fetch_Array($recs)) {
    $rec = array();
    reset($columns);
    foreach ($columns as $column)
      $rec[$column] = $data[$column];

    # this is likely to require some memory, do we need to intercept that?
    # hmm
    array_push($records,$rec);
  }

  Sql_Drop_Table($table);
  Sql_Create_Table($table,$tablestructure);

  foreach ($records as $record) {
#    while (list($key,$val) = each ($record))
#      print "$key => $val<br/>\n";
    $collist = "";
    $vallist = "";

    reset($tablestructure);
    while (list($column, $value) = each ($tablestructure)) {
      if ($column != "primary key" && $column != "unique") {
        $collist .= "$column,";
        $vallist .= sprintf('"%s",',addslashes($record[$column]));
      }
    }
    $collist = substr($collist,0,-1);
    $vallist = substr($vallist,0,-1);
    $query = "replace into $table ($collist) values($vallist)";
    Sql_Query($query);
  }
}

# This one is a bit better, as it writes data to file instead of memory, but
# its not brilliant
function upgradeTable($table,$tablestructure) {
  global $tmpdir;
  $columns = array();
  $records = array();

  $fname = tempnam($tmpdir,"");
  $fp = fopen($fname,"w");
  if (Sql_Table_Exists($table)) {
    $cols = Sql_Query("show columns from $table");
    while ($row = Sql_Fetch_Row($cols))
      array_push($columns,$row[0]);

    #$fp = tmpfile();
#    print "Writing tempfile $fname<br/>";
    $recs = Sql_Query("select * from $table");
    while ($data = Sql_Fetch_Array($recs)) {
      reset($columns);
      foreach ($columns as $column) {
        fwrite($fp,"$column:".base64_encode($data[$column])."\n");
      }
      fwrite($fp,"--\n");
    }
  }
  fclose($fp);

  Sql_Drop_Table($table);
  Sql_Create_Table($table,$tablestructure);

  $fp=fopen($fname,"r");
  if (!$fp) {
    unlink($fname);
    return 0;
  }
#  print "Reading tempfile<br/>";
  while (!feof($fp)) {
    # read one record
    $buffer = "";
    $record = array();
    $buffer = fgets($fp, 4096);
    while (!feof ($fp) && !ereg("^--",$buffer)) {
      list($column,$value) = explode(":",$buffer);
      if ($column && $value)
        $record[$column] = base64_decode($value);
      $buffer = fgets($fp, 4096);
    }

    $collist = "";
    $vallist = "";
    if (sizeof($record)) {
      reset($tablestructure);
      while (list($column, $value) = each ($tablestructure)) {
        if ($column != "primary key" && $column != "unique") {
          $collist .= "$column,";
          $vallist .= sprintf('"%s",',addslashes($record[$column]));
        }
      }
      $collist = substr($collist,0,-1);
      $vallist = substr($vallist,0,-1);
      $query = "replace into $table ($collist) values($vallist)";
#      print $query . "<br/>";
      if (!Sql_Query($query)) {
        unlink($fname);
        return 0;
      }
    }
  }
  fclose($fp);
  unlink($fname);
  return 1;
}

function Help($topic, $text = '?') {
  return sprintf('<span class="helplink"><a href="help/?topic=%s" class="helpdialog" target="_blank">%s</a></span>', $topic, $text);
}

# Debugging system, needs $debug = TRUE and $verbose = TRUE or $debug_log = {path} in config.php
# Hint: When using log make sure the file gets write permissions 
#
function dbg($variable, $description = 'Value', $nestingLevel = 0) {
//  smartDebug($variable, $description, $nestingLevel); //TODO Fix before release!
//  return;
  
  global $config;
 # if (!$config["debug"])
#   $er = error_reporting(0);
  if (ini_get("safe_mode"))
    return;

  if (isset($config["debug"]) && !$config["debug"]) {
    return;
  } 
    
  if (is_array($variable)) {
    $tmp = $variable;
    $variable = '';
    foreach ($tmp as $key => $val) {
      $variable .= $key.'='.$val.';';
    }
  }

  $msg = $description.': '.$variable;

  if (isset($config["verbose"]) && $config["verbose"]) 
    print "\n".'DBG: '.$msg.'<br/>'."\n";
  elseif (isset($config["debug_log"]) && $config["debug_log"]) {
    $fp = @fopen($config["debug_log"],"a");
    $line = "[".date("d M Y, H:i:s")."] ".$_SERVER["REQUEST_METHOD"].'-'.$_SERVER["REQUEST_URI"].'('.$GLOBALS["pagestats"]["number_of_queries"].") $msg \n";
    @fwrite($fp,$line);
    @fclose($fp);
  #  $fp = fopen($config["sql_log"],"a");
  #  fwrite($fp,"$line");
  #  fclose($fp);
  }
}

#
#
function PageData($id) {
  global $tables;
  $req = Sql_Query(sprintf('select * from %s where id = %d',$tables["subscribepage_data"],$id));
  if (!Sql_Num_Rows($req)) {
    $data = array();
    $data["header"] = getConfig("pageheader");
    $data["footer"] = getConfig("pagefooter");
    $data["button"] = 'Subscribe';
    $data['attributes'] = '';
    $req = Sql_Query(sprintf('select * from %s order by listorder',$GLOBALS['tables']['attribute']));
    while ($row = Sql_Fetch_Array($req)) {
      $data['attributes'] .= $row['id'].'+';
      $data[sprintf('attribute%03d',$row['id'])] = '';
      foreach (array (
          'id',
          'default_value',
          'listorder',
          'required'
        ) as $key) {
        $data[sprintf('attribute%03d',$row['id'])] .= $row[$key].'###';
      }
    }
    $data['attributes'] = substr($data['attributes'],0,-1);
    $data['htmlchoice'] = 'checkforhtml';
    $lists = array();
    $req = Sql_Query(sprintf('select * from %s where active order by listorder',$GLOBALS['tables']['list']));
    while ($row = Sql_Fetch_Array($req)) {
      array_push($lists,$row['id']);
    }
    $data['lists'] = join(',',$lists);
    $data['intro'] = $GLOBALS['strSubscribeInfo'];
    $data['emaildoubleentry'] = 'yes';
    $data['thankyoupage'] = '';
//    $data['rssdefault'] = 'daily'; //Leftover from the preplugin era, to be moved to plugin somehow
//    $data['rssintro'] = $GLOBALS['I18N']->get('Please indicate how often you want to receive messages'); //Leftover from the preplugin era, to be moved to plugin somehow
//    $data['rss'] = join(',',array_keys($GLOBALS['rssfrequencies'])); //Leftover from the preplugin era, to be moved to plugin somehow
    return $data;
  }
  while ($row = Sql_Fetch_Array($req)) {
    $data[$row["name"]] = preg_replace('/<\?=VERSION\?>/i', VERSION, $row["data"]);
  }

  if (!isset ($data['lists']))
    $data['lists'] = '';
  if (!isset ($data['emaildoubleentry']))
    $data['emaildoubleentry'] = '';
  if (!isset ($data['rssdefault']))
    $data['rssdefault'] = '';
  if (!isset ($data['rssintro']))
    $data['rssintro'] = '';
  if (!isset ($data['rss']))
    $data['rss'] = '';
  if (!isset ($data['lists']))
    $data['lists'] = '';
  return $data;
}

function PageAttributes($data) {
  $attributes = explode('+',$data["attributes"]);
  $attributedata = array();
  if (is_array($attributes)) {
    foreach ($attributes as $attribute) {
      if (isset($data[sprintf('attribute%03d',$attribute)])) {
        list ($attributedata[$attribute]["id"], $attributedata[$attribute]["default_value"], $attributedata[$attribute]["listorder"], $attributedata[$attribute]["required"]) = explode('###', $data[sprintf('attribute%03d', $attribute)]);
        if (!isset($sorted) || !is_array($sorted)) {
          $sorted = array();
        }
        $sorted[$attributedata[$attribute]["id"]] = $attributedata[$attribute]["listorder"];
      }
    }
    if (isset($sorted) && is_array($sorted)) {
      $attributes = $sorted;
      asort($attributes);
    }
  }
  return array (
    $attributes,
    $attributedata
  );
}


function formatDate ($date,$short = 0) {
  $months = array ("",$GLOBALS['I18N']->get("January"), $GLOBALS['I18N']->get("February"), $GLOBALS['I18N']->get("March"), $GLOBALS['I18N']->get("April"), $GLOBALS['I18N']->get("May"), $GLOBALS['I18N']->get("June"), $GLOBALS['I18N']->get("July"), $GLOBALS['I18N']->get("August"), $GLOBALS['I18N']->get("September"), $GLOBALS['I18N']->get("October"), $GLOBALS['I18N']->get("November"), $GLOBALS['I18N']->get("December"));
  $shortmonths = array ("",$GLOBALS['I18N']->get("Jan"),$GLOBALS['I18N']->get("Feb"),$GLOBALS['I18N']->get("Mar"), $GLOBALS['I18N']->get("Apr"), $GLOBALS['I18N']->get("May"), $GLOBALS['I18N']->get("Jun"), $GLOBALS['I18N']->get("Jul"), $GLOBALS['I18N']->get("Aug"), $GLOBALS['I18N']->get("Sep"), $GLOBALS['I18N']->get("Oct"), $GLOBALS['I18N']->get("Nov"), $GLOBALS['I18N']->get("Dec"));
  $year = substr($date,0,4);
  $month = substr($date,5,2);
  $day = substr($date,8,2);
  $day = ereg_replace("^0","",$day);

  if ($date) {
    if ($short)
      return $day . "&nbsp;" . $shortmonths[intval($month)] . "&nbsp;" . $year;
    else
      return $day . "&nbsp;" . $months[intval($month)] . "&nbsp;" . $year;
   }
}

$oldpoweredimage = 'iVBORw0KGgoAAAANSUhEUgAAAFgAAAAfCAMAAABUFvrSAAAABGdBTUEAALGPC/xhBQAAAMBQTFRFmQAAZgAAmgICmwUFnAgInQsLnxAQbw4OohYWcBERpBwcpiIiqCcnqiwsfCAgrDAwrjU1rzg4sTs7iTAws0FBtEVFtklJuU9Pu1VVn0pKkEREvltbtFxcwWRkw2trm1ZWrGNjx3V1y3x8zoWFqW5u0I6O15ycuoqK3aysxZqa3rm55s3N8t3d9+zs+fHx5t/f/Pf3/fr6////7+/vz8/PtbW1j4+Pb29vVVVVRkZGKioqExMTDg4OBwcHAwMDAAAAB4LGQwAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxIAAAsSAdLdfvwAAAAHdElNRQfSBAITGhB/UY5ZAAAD2ElEQVR4nI2VC3uiOhCGoVqq9YbcZHGxIoI0SLGhIJdt8///1c4kHnVPhTpPK4TPvEzmpkTvsiK/73vckmAuSdJ93/26G5wEhsQN7uuaVTSrWP1BGT1WtCpgUWUf7FhVX1WWVZ/Hz/Qu6ltoSf8ZLFnxwfKypPBXZ02dsrQss7oovnJ+PZa0au6gHqJFT5KuwDmjGctZzp09lux4pF911RRFTT/x+geU8ifqe2T3pX8MEsM+ioY2BThHyyavm5TWRQbhKMS1KVJQOo24ivR/o/RY101Oi4Yd4SUVBoTmNaCqnOYV0POqKLtyR7zBNyoHVz+402nxZqI83uIi+KdSWjtOfFPYh+boeaB8D4N0Xx3LsnzjaRK5hqZOkNwK7u4rIsv6Nyrxl0t7YRmc3ApmneCdLK//efAWhxvPW63cpc3JreCU1QyrNj/31+tul5K1s+brtSzv0p3j7IS0ffHW+lT3kO3aljYbP7eBcyhk6BAKnXGJ6gv8y0NMmg4eD3G1pe97iIvs4OIpCjbearkw1PGoDQzFm7OU5U124sbI3G6HIriIcXY6pnAf+VzCF+kHCIhrm/NJK7iqM+gKdmmvV+Er8hPMHcY44bURrbn0HqGU+OAyxKIV3JQweWh9dphu8dgiCARzNwXujrsfvfCIkGiKUrBBsMvnpAl4xTThBm10qeO8uTQgBDE+XQkF1I4eyBr9fiM6SntC+DsjDqY+d9CTzAQcmHGCdwFX58xdOmKIlClHRQ7yee4gRoQ84VMOnp/BJFaUfcRvpZudF5/AcB2eYns6+z4QKxKgREOevDPYo6E7kjrAkDtw57B38PTgowOIULi65RIhXDpAVUC5ncGSBwF0O8C4W08xqk+pSOQ+XInc/bqWYlEUZ7BtSkpEO8DgzlTm9koPOn7G/i90MQn1a8kX/UFDKAMe48S2430b+BDjqVNsvCmBcPIERp6OuYuDaykCLrYH34a0WQTBmt0EH8hm6f7mhRu8QsCSEGYNFJHvuitYktW15AJX6x6bwt7JSlWNxRJO/ULf/E0QBjDAwGy05dJdeSfJ55INXJhAg9ZfEGHEfVaexzPNssWpcSyCTwvLsngvWQt76QqJzzUcmXPO7QLHq4H00FcGo8ncsHjFRq4Y5NocTFXVuWYAWkh8EoO76onbbwHHHh+oCAaX54aubxPqA9U0tNlsMpmMwSYzVTNMIeErTXCXx/fxsd+7Cd6MTzcPvcfBYIRkKwxD2KnB1vFo9CxsNJ6A2yZItmWdNOT2+73b4LMBGFzG/RrYXBU7uSkKfKA0UyEwVyJwe72Hh1u4v1tVRVPPqSx/AAAAAElFTkSuQmCC';
$oldnewpoweredimage = 'iVBORw0KGgoAAAANSUhEUgAAAFgAAAAfCAYAAABjyArgAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsSAAALEgHS3X78AAAAB3RJTUUH0wcfFB4OyvJGjAAACCJJREFUeJztmj1sG8kVx3+zS+qDsi+hIV9yCXAClIo13foqOXYbAVJjVynkLvBVEpAi1RlUZVcBJKRMkxNgd0EcscoBqW6LJAWDQ0xARqoTIZ7Ppj5I7k6Kt48zu/yQZFmxcfADCO7ODmd3f/OfN2/e0ACWD3ZpVgCwW+/6Md4XM2AMBAUIpqEwA1M/huJVmPpIzoMimIJUt32IT6D3Grrfw3ELui8h6YGNMWuW4N2+EGw8MRPPL988qGERCnMw9SOYnYfZn0Dpp1D6GKbLArowB+G01FfQ/WMBHXdds1YcQwZwtAfNlnyr+efNFrQPXV2/rH0I9cZwW82WnNcb7reZe73IeqjohR1c8++TP76wGQMmgCCEcAbC2RzYT2DuZzAzL2DDaTCh/DbpQb8jaj1qQbcN3e8gPpJrSTy4TcG/58ZTWJwXECtVUdJOZFmcN+xEsHhdYFQ/hdVteP4FbP4F1u/A/T/CUgU2n8HuA9fW2k245V3bugfRnmEnspRLw+8d7cFOZIheWLbuud/UG4Zmy1JduDBZp1gTQjglcBXi1EfyCYopVA/RwCV0oHcI/ddwcgDdV5CcgE3AxqJeMwIwQG1Zvle3BeaXa1AuWW48hN0UZPsQ1j5zKtyJBKaaKlnAuPLFealbb1h2H0g7q9vZ+y9eh9qypd6QuksV+Y72LOt33pSpScEGqWqnBWBhRoZ88SoU5+QTzoqqM2BjSPqiWvW33ZcCWlWrYGEAdyRgkBdXdfnH5ZJ82h3D2meWjScCAKC6IGpdqZJRZrkkYNdvO1eiHTNKwWrqItZuSqc2W9lOPDdUE0IwJYotXknBlqBwJZ28pp2q82Djo1S1HXEHvdfid5MTuW4t44KxIcD6Mlt33bm+KAjIaM+yOC+gassCanVblAbiItSqC1CODDceuhGxflvqjwLW7sCtxwzqqmlHTjRjwBpRkAlEpQo1nJFP8aoALV5JJ6sp1wFqo8D2XsmnfyTlNkl97eQo1wBWw7Rbj7Nw3gdrtqSTxVXlr6Zj0YxRqkINp0WpxZKUK/igmG0u6UHSFT/bPxoGm3SH3cEEM/dzCl6pXoDEJVlzH9ZuGsoliwx5cGBPgVosQVhy8es4tdrEgVUfG58Mg00SRLFnX5tlFPzeWMrSzRbesFdAppDO9KkS/eEfTE2GChIRJLH40f6xiwp0Iou7nmKTiX52nA0p+N2acV865GEyUPWhxbnsNVNIV2OB+GS1vFrVDSRdiQriYzd5DUIuuEg24fIBD9QIAyVqmc0pExxMEwgwEw4DzatU6w8Nf7JQk54os/9aFKtuIO6mE1c6uWHP5GPPYucDrPccWs2abLkPMMDBgyxISIdwmMaeKUxdhhavQpgehzOpKtM8wSigg+f0oNo4Xcqm6uy9kmM9t/0LuYHTTAAbfxiRBWg91eUzF6PAQfalNWAPigJRj03oQKoyFab6zcyQD+XBxgFV1flKHQVVfat2groBb/X1Nk3ePpw5vaa/shkFUxWo1wflHkTIgvQVq0H+oEMmAAUPUOIC/v6RHOvw96FqPf1N3g1cUo5J3mZmwhIp8MHmPIpCAwEHAs8GMsGYFLoJUoC5KMCfyGAyTBgGmvS9iUknro4Xz3pKfYMQ622YECv9XFJ1k0xBqQU5RechaWecFaKawkwsEIuP1MlnFND42LmEpOfq56HmXd//yYTC7DU3ceTNJsPlOnkBmac+Dd4oS2Fs/OFfbP7pm6HLa7/8mNrdTyiXYtqvjrj26+eUS3Dw+2sp+C7tTsK135xI+eNAzj8fvtXiPNSWDSvVrIo3n0lSatwq1tyXa6ct1yXHkm1fAAfT2eH+NszYVEGp2cR9+yqzCSR9on9/C0D1FyXKc9Jp0X86bP/1W8pmn9rdeaJ/7kudBaD3vbSXJETNxJXbZJA3LpcYpDc1ybS6bTl4NDnRlLfdB5wpTbrxFJYq2c47fxysQ9gVQIIABQdQ63oQXejUh7gvxwDxCfV/fAfA17+bGySsd/5+yOq2laR89yXRC6m+VCGTaMmUe+frdySxpHbjoUCO9s6YPBph7UPY/kqOF+edWqM9udbcl2PtEAGcnIxvUWGBAwbuG9LgHJcISRIwyRBEiTnVp/bS38RE3xwA6UN1Xw46pdmy6YsAcS+zo1FvWO/YvTDYQT09VzDN/bT8+vjXHWWaBFuqyHG7I23UG5b124basmXjqeu8Zsu5GwF83HJhk69QBQUCy7fE239SeOAAaowJorZBmafu1FRx7Q5s/lnalZ0NKZcklAO38WQ0iOqCUxMIgGZL8tc7kaV9KJDOlVf2rN6Qtp9/IW3IpoDcc/eBwF+qZEeNAH793xHJEC808s/VBvtOGqhrvWGAQ6rXoD5d5zf35aTZysIrlyTfvFRJFZgm6f2djXbHsPnMDhL7Wg/cUFYVL1WyOebzWnVB7n/rkbS1VDFs3Zsc9gngk/boq0P+VsuTCedeSGS9Mv0y2SJwG5+1Zdnvg+wEBU6V1YWsQuoNy+YzV9evV/uVq6dwLmLlEnz9W9mH3Ilg+yvZeNj9fPyoEMD94/GtZpbN+Ys2ezixLmPjUPWhPri85SeyceV6vlJ984lsnDVb8qxb93Tz1u3kjHt2/QfF+FbPuvB5wyDeV9xZ6vkT16jycfVOs/zfDkAmMl+ZzX236Vv91P1lQUedPs9wFPEOTRXnP+TIeoOOsBPLx9U79Tn23F6gWm05q8ylipRt/81twq7fcSNlpSpzSL0BB4+k7P3c0fiBmLk/nID8YG/ZzueoPti57X8R0X5CmAXRQQAAAABJRU5ErkJggg==';
$newpoweredimage = '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';

function FileNotFound() {
  ob_end_clean();
  header("HTTP/1.0 404 File Not Found");
  printf('<html><head><title>404 Not Found</title></head><body><h1>Not Found</h1>The requested document was not found on this server<br/>Please contact the <a href="mailto:%s?subject=File not Found: %s">Administrator</a><p><hr><address><a href="http://tincan.co.uk/phplist" target="_tincan">phplist</a> version %s</address></body></html>', getConfig("admin_address"), $_SERVER["REQUEST_URI"], VERSION);
  exit;
}

function findMime($filename) {
  list($name,$ext) = explode(".",$filename);
  if (!$ext || !is_file(MIMETYPES_FILE)) {
    return DEFAULT_MIMETYPE;
  }
  $fp = @fopen(MIMETYPES_FILE,"r");
  $contents = fread($fp,filesize(MIMETYPES_FILE));
  fclose($fp);
  $lines = explode("\n",$contents);
  foreach ($lines as $line) {
    if (!ereg("#",$line) && !preg_match("/^\s*$/",$line)) {
      $line = preg_replace("/\t/"," ",$line);
      $items = explode(" ",$line);
      $mime = array_shift($items);
      foreach ($items as $extension) {
        $extension = trim($extension);
        if ($ext == $extension) {
          return $mime;
        }
      }
    }
  }
  return DEFAULT_MIMETYPE;
}

function excludedDateForRepetition($date) {
 if (!is_array($GLOBALS["repeat_exclude"]))
   return 0;
  foreach ($GLOBALS["repeat_exclude"] as $exclusion) {
    $formatted_value = Sql_Fetch_Row_Query(sprintf('select date_format("%s","%s")',$date,$exclusion["format"]));
    foreach ($exclusion["values"] as $disallowed) {
      if ($formatted_value[0] == $disallowed) {
        return 1;
      }
    }
  }
  return 0;
}

function delimited($data){
  $delimitedData="";
  reset($data);
  while (list ($key, $val) = each ($data)) {
    $delimitedData .= $key.'KEYVALSEP'.$val.'ITEMSEP';
  }
  $length = strlen($delimitedData);
  return substr($delimitedData, 0, -7);
}

function parseDelimitedData($value) {
  $data = array();
  $rawdata = explode('ITEMSEP',$value);
  foreach ($rawdata as $item) {
    list($key,$val) = split('KEYVALSEP',$item);
    $data[$key] = ltrim($val);
  }
  return $data;
}

function repeatMessage($msgid) {
#  if (!USE_REPETITION && !USE_rss) return;

  # get the future embargo, either "repeat" minutes after the old embargo
  # or "repeat" after this very moment to make sure that we're not sending the
  # message every time running the queue when there's no embargo set.
$msgdata = Sql_Fetch_Array_Query(
    sprintf('select *,date_add(embargo,interval repeatinterval minute) as newembargo,
      date_add(now(),interval repeatinterval minute) as newembargo2, date_add(embargo,interval repeatinterval minute) > now() as isfuture
      from %s where id = %d and repeatuntil > now()',$GLOBALS["tables"]["message"],$msgid));
  if (!$msgdata["id"] || !$msgdata["repeatinterval"]) return;

  # copy the new message
  $query
  = ' insert into ' . $GLOBALS['tables']['message']
  . '    (entered)'
  . ' values'
  . '    (current_timestamp)';
  Sql_Query($query);
  $id = Sql_Insert_Id($GLOBALS['tables']['message'], 'id');
  require dirname(__FILE__).'/structure.php';
  if (!is_array($DBstruct["message"])) {
    logEvent("Error including structure when trying to duplicate message $msgid");
    return;
  }
  foreach ($DBstruct["message"] as $column => $rec) {
    if ($column != "id" && $column != "entered" && $column != "sendstart") {
      Sql_Query(sprintf('update %s set %s = "%s" where id = %d',
        $GLOBALS["tables"]["message"],$column,addslashes($msgdata[$column]),$id));
     }
  }
  $req = Sql_Query(sprintf('select * from %s where id = %d',
    $GLOBALS['tables']['messagedata'],$msgid));
  while ($row = Sql_Fetch_Array($req)) {
    setMessageData($id,$row['name'],$row['data']);
  }

  # check whether the new embargo is not on an exclusion
  if (is_array($GLOBALS["repeat_exclude"])) {
    $repeatinterval = $msgdata["repeatinterval"];
    $loopcnt = 0;
    while (excludedDateForRepetition($msgdata["newembargo"])) {
      $repeat += $msgdata["repeatinterval"];
      $loopcnt++;
      $msgdata = Sql_Fetch_Array_Query(
          sprintf('select *,date_add(embargo,interval %d minute) as newembargo,
            date_add(current_timestamp,interval %d minute) as newembargo2, date_add(embargo,interval %d minute) > current_timestamp as isfuture
            from %s where id = %d and repeatuntil > current_timestamp',$repeatinterval,$repeatinterval,$repeatinterval,
            $GLOBALS["tables"]["message"],$msgid));
      if ($loopcnt > 15) {
        logEvent("Unable to find new embargo date too many exclusions? for message $msgid");
        return;
      }
    }
  }
  # correct some values
  if (!$msgdata["isfuture"]) {
    $msgdata["newembargo"] = $msgdata["newembargo2"];
  }

  Sql_Query(sprintf('update %s set embargo = "%s",status = "submitted",sent = "" where id = %d',
      $GLOBALS["tables"]["message"],$msgdata["newembargo"],$id));
      
  list($e['year'],$e['month'],$e['day'],$e['hour'],$e['minute'],$e['second']) = 
    sscanf($msgdata["newembargo"],'%04d-%02d-%02d %02d:%02d:%02d');
  unset($e['second']);  
  setMessageData($id,'embargo',$e);
      
  foreach (array("processed","astext","ashtml","astextandhtml","aspdf","astextandpdf","viewed", "bouncecount") as $item) {
    Sql_Query(sprintf('update %s set %s = 0 where id = %d',
        $GLOBALS["tables"]["message"],$item,$id));
  }

  # lists
  $req = Sql_Query(sprintf('select listid from %s where messageid = %d',$GLOBALS["tables"]["listmessage"],$msgid));
  while ($row = Sql_Fetch_Row($req)) {
    Sql_Query(sprintf('insert into %s (messageid,listid,entered) values(%d,%d,current_timestamp)',
      $GLOBALS["tables"]["listmessage"],$id,$row[0]));
  }

  # attachments
  $req = Sql_Query(sprintf('select * from %s,%s where %s.messageid = %d and %s.attachmentid = %s.id',
    $GLOBALS["tables"]["message_attachment"],$GLOBALS["tables"]["attachment"],
    $GLOBALS["tables"]["message_attachment"],$msgid,$GLOBALS["tables"]["message_attachment"],
    $GLOBALS["tables"]["attachment"]));
  while ($row = Sql_Fetch_Array($req)) {
    if (is_file($row["remotefile"])) {
      # if the "remote file" is actually local, we want to refresh the attachment, so we set
      # filename to nothing
      $row["filename"] = "";
    }

    Sql_Query(sprintf('insert into %s (filename,remotefile,mimetype,description,size)
      values("%s","%s","%s","%s",%d)',
      $GLOBALS["tables"]["attachment"],addslashes($row["filename"]),addslashes($row["remotefile"]),
      addslashes($row["mimetype"]),addslashes($row["description"]),$row["size"]));
    $attid = Sql_Insert_Id($GLOBALS['tables']['attachment'], 'id');
    Sql_Query(sprintf('insert into %s (messageid,attachmentid) values(%d,%d)',
      $GLOBALS["tables"]["message_attachment"],$id,$attid));
  }
  logEvent("Message $msgid was successfully rescheduled as message $id");
}

function versionCompare($thisversion,$latestversion) {
  # return 1 if $thisversion is larger or equal to $latestversion

  list($major1,$minor1,$sub1) = sscanf($thisversion,'%d.%d.%d');
  list($major2,$minor2,$sub2) = sscanf($latestversion,'%d.%d.%d');
  if ($major1 > $major2) return 1;
  if ($major1 == $major2 && $minor1 > $minor2) return 1;
  if ($major1 == $major2 && $minor1 == $minor2 && $sub1 >= $sub2) return 1;
  return 0;
}

function formatTime($time,$short = 0) {
  return $time;
}

function cleanArray($array) {
  $result = array();
  if (!is_array($array)) return $array;
  foreach ($array as $key => $val) {
    ## 0 is a valid key
    if (isset($key) && !empty($val)) {
      $result[$key] = $val;
    }
  }
  return $result;
}

function formatDateTime ($datetime,$short = 0) {
  $date = substr($datetime,0,10);
  $time = substr($datetime,11,8);
  return formatDate($date,$short). " ".formatTime($time,$short);
}

function cl_output($message) {
  if ($GLOBALS["commandline"]) {
    ob_end_clean();
    print strip_tags($message) . "\n";
    ob_start();
  } 
}

function phplist_shutdown () {
#  output( "Script status: ".connection_status(),0); # with PHP 4.2.1 buggy. http://bugs.php.net/bug.php?id=17774
  $status = connection_status();
  if ($GLOBALS["mail_error_count"]) {
   $message = "Some errors occurred in the PHPlist Mailinglist System\n"
    ."URL: {$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}\n"
    ."Error message(s):\n\n"

    .$GLOBALS["mail_error"];
    $message .= "\n==== debugging information\n\nSERVER Vars\n";
    if (is_array($_SERVER))
    while (list($key,$val) = each ($_SERVER))
      if ($key != "password")
        $message .= $key . "=" . $val . "\n";
    sendMail(getConfig("report_address"),$GLOBALS["installation_name"]." Mail list error",$message,"");
  }

#  print "Phplist shutdown $status";
#  exit;
}

function trimArray($array) {
  $result = array();
  if (!is_array($array)) return $array;
  foreach ($array as $key => $val) {
    $testval = trim($val);
    if (isset($key) && !empty($testval)) {
      $result[$key] = $val;
    }
  }
  return $result;
}

register_shutdown_function("phplist_shutdown");

function secs2time($secs) {
  $years = $days = $hours = $mins = 0;
  $hours = (int)($secs / 3600);
  $secs = $secs - ($hours * 3600);
  if ($hours > 24) {
    $days = (int)($hours / 24);
    $hours = $hours - (24 * $days);
  }
  if ($days > 365) { ## a well, an estimate
    $years = (int) ($days / 365);
    $days = $days - ($years * 365);
  }
  $mins = (int)($secs / 60);
  $secs = (int)($secs % 60);

  $res = '';
  if ($years) {
    $res .= $years .' years';
  }
  if ($days) {
    $res .= ' '.$days .' days';
  }
  if ($hours) {
    $res .= ' '.$hours . " hours";
  }
  if ($mins) {
    $res .= " ".$mins . " mins";
  }
  if ($secs) {
    $res .= " ".sprintf('%02d',$secs) . " secs";
  }
  return $res;
}

function cleanCommaList($sList) {
  if (strpos($sList,',') === false) return $sList;
  $aList = explode(',',$sList);
  return join(',',trimArray($aList));
}

function printobject($object) {
  if (!is_object($object)) {
    print "Not an object";
    return;
  }
  $class = get_class($object);
  print "Class: $class<br/>";
  $vars = get_object_vars ($object);
  print "Vars:";
  printArray($vars);
}

function printarray($array){
  if (is_object($array)) return printObject($array);
  if (!is_array($array)) return;
  while(list($key,$value) = each($array)){
   if (is_array($value)) {
     echo $key."(array):<blockquote>";
     printarray($value);//recursief!!
     echo "</blockquote>";
   } elseif (is_object($value)){
     echo $key."(object):<blockquote>";
     printobject($value);
     echo "</blockquote>";
   } else{
     echo $key."==>".$value."<br />";
   }
  }
}

function Paging($base_url,$start,$total,$numpp = 10,$label = "") {
  $page = 1;
  $data = '';#PagingPrevious($base_url,$start,$total,$numpp,$label);#.'&nbsp;|&nbsp;';
  if (!isset($GLOBALS['config']['paginglabeltitle'])) {
    $labeltitle = $label;
  } else {
    $labeltitle = $GLOBALS['config']['paginglabeltitle'];
  }

  for ($i = 0;$i<=$total;$i+=$numpp) {
    if ($i == $start)
      $data .= sprintf('<a class="current paging-item" title="%s %s" class="paging-item">%s%s</a>',$labeltitle,$page,$label,$page);
    else
      $data .= sprintf('<a href="%s&amp;s=%d" title="%s %s" rel="nofollow" class="paging-item">%s%s</a>',$base_url,$i,$labeltitle,$page,$label,$page);
    $page++;
  }
  if ($page == 1)
    return "";
 # $data .= PagingNext($base_url,$start,$total,$numpp,$label,$page);
  return '<div class="pagingwrapper"><a class="prev browse left">&lt;&lt;</a><div class="paging"><div class="items">'.$data.'</div></div><a class="next browse right">&gt;&gt;</a></div>';
}

function PagingNext($base_url,$start,$total,$numpp,$label = "") {
  if (!isset($GLOBALS['config']["pagingnext"])) $GLOBALS['config']["pagingnext"] = '&gt;&gt;';
  if (($start + $numpp - 1) < $total)
    $data = sprintf('<a href="%s&amp;s=%d" title="Next" class="pagingnext paging-item" rel="nofollow">%s</a>',$base_url,$start + $numpp,$GLOBALS['config']["pagingnext"]);
  else
    $data = sprintf('<a class="pagingnext paging-item">%s</a>',$GLOBALS['config']["pagingnext"]);
  return $data;
}

function PagingPrevious($base_url,$start,$total,$numpp,$label = "") {
  if (!isset($GLOBALS['config']["pagingback"])) $GLOBALS['config']["pagingback"] = '&lt;&lt;';
  $page = 1;
  if ($start > 1)
    $data = sprintf('<a href="%s&amp;s=%d" title="Previous" class="pagingprevious paging-item" rel="nofollow">%s</a>',$base_url,$start - $numpp,$GLOBALS['config']["pagingback"]);
  else
    $data = sprintf('<a class="pagingprevious paging-item">%s</a>',$GLOBALS['config']["pagingback"]);
  return $data;
}


class timer {
  var $start;
  var $previous = 0;

  function timer() {
    $now =  gettimeofday();
    $this->start = $now["sec"] * 1000000 + $now["usec"];
  }

  function elapsed($seconds = 0) {
    $now = gettimeofday();
    $end = $now["sec"] * 1000000 + $now["usec"];
    $elapsed = $end - $this->start;
    if ($seconds) {
      return sprintf('%0.10f',$elapsed / 1000000);
    } else {
      return sprintf('%0.10f',$elapsed);
    }
  }

  function interval($seconds = 0) {
    $now = gettimeofday();
    $end = $now["sec"] * 1000000 + $now["usec"];
    if (!$this->previous) {
      $elapsed = $end - $this->start;
    } else {
      $elapsed = $end - $this->previous;
    }
    $this->previous = $end;

    if ($seconds) {
      return sprintf('%0.10f',$elapsed / 1000000);
    } else {
      return sprintf('%0.10f',$elapsed);
    }
  }

}
