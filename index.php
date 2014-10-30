<html>
<head>
<style type="text/css">
.even { background-color:#FFFFFF; }
.odd { background-color:#DEDEDE;}

table.sample {
    border-width: 0px 0px 0px 0px;
    border-spacing: 0px;
    border-style: outset outset outset outset;
    border-color: gray gray gray gray;
    border-collapse: separate;
    background-color: white;
    font-size : 85%;
}
table.sample th {
    border-width: 1px 1px 1px 1px;
    padding: 1px 1px 1px 1px;
    border-style: inset inset inset inset;
    border-color: gray gray gray gray;
    -moz-border-radius: 0px 0px 0px 0px;
    font-size : 85%;
}
table.sample td {
    border-width: 1px 1px 1px 1px;
    padding: 1px 1px 1px 1px;
    border-style: inset inset inset inset;
    border-color: gray gray gray gray;
    -moz-border-radius: 0px 0px 0px 0px;
    font-size : 85%;
}

.security {
    color :red;
}
</style>
<title>Sakai SAK Summary</title>
</head>
<body>
<!--
<br>
Todo: <ul>
<li><s>Form will work</s></li>
<li>Caching improved</li>
<li>Add additional columns (<s>version/priority/</s>branch status)</li>
<li>Sortable columns for html version (What columns sortable: SAK Number, Priority, Status, Fix Version)</li>
<li>Confluence and/or rwiki export</li>
<li><s>Remove pre from svn message (convert to &lt;br&gt;)</s></li>
</ul>
-->
<!--
<b>This form doesn't actually work *yet*. I also have some concerns about large numbers of results.</b>
-->

<?php
$sr = $_GET['sr'];
$er = $_GET['er'];
if (!$er)
	$er = "HEAD";
$tag = $_GET['tag'];
if (!$tag)
$tag = "/svn/assignment/trunk/";
if (!$sr) 
	$sr = "HEAD";



#Append svn to tag if they just put the branch
    if ($tag) {
        if (!preg_match("/(svn|contrib)/",$tag)) {
            $tag = "/svn/$tag"; 
        }
    }

$format = $_GET['format'];
?>
<form method=get>
Sakai SVN URL: https://source.sakaiproject.org<input type=text name="tag" value="<?php echo $tag?>">
Start Revision: <input type=text name="sr" value="<?php echo $sr ?>">
End Revision: <input type=text name="er" value="<?php echo $er ?>">
Output Format: <select name="format"><option <?php if ($format =="HTML") echo "selected" ?>>HTML</option><option <?php if ($format =="rwiki") echo "selected"?>>rwiki</option></select>
<input type=submit value="Get these changes">
</form>
<?php

require_once("sabrecache.php");


function gentime() {
    static $a;
    if($a == 0) $a = microtime(true);
    else return (string)(microtime(true)-$a);
}
    //http://www.edmondscommerce.co.uk/blog/php/converting-simplexml-objects-into-arrays/
    function sxArray($obj){
        $arr = (array)$obj;
        if(empty($arr)){
            $arr = "";
        } else {
            foreach($arr as $key=>$value){
                if(!is_scalar($value)){
                    $arr[$key] = sx_array($value);
                }
            }
        }
        var_dump($arr);
        return $arr;
    }

    //Implode doesn't work with simple xml
    function xmlimplode($glue="", $var){
        if ($var){
            foreach ($var as $value){
                $array[]=trim(strval($value));
            }
            return implode($glue, $array);
        }
        else return false;
    }

class SakSummary {
    protected $cache;
    public $cachetime;
    public $authors;
    public $types;
    public $maxlogs;
    public $ignoremsub;

    public function __construct($cachetime = 600, $maxlogs=500) {
        $this->cache = new Sabre_Cache_Generic;
        $this->cachetime=$cachetime;
        $this->maxlogs=$maxlogs;
	$this->ignoremsub=true;
    }

    function getSAKS($url, $startrev, $endrev) {
        /*
        if ($cacheval = $this->cache->fetch("$url-$startrev-$endrev")) {
            return $cacheval;
        }
        */
        #URL must start with "source.sakaiproject.org"
        #Should convert viewsvn to svn or contrib as appropriate
        #viewsvn just converts to svn
        #contrib converts viewsvn to contrib and removes contrib from the end
        $retval = array();
        print "Getting SAK's for $url from $startrev to $endrev:";
	if (!is_numeric($endrev)) 
		$endrev=SVN_REVISION_HEAD;
	if (!is_numeric($startrev)) 
		$startrev=SVN_REVISION_HEAD;
        $logs= svn_log($url, $startrev, $endrev, $this->maxlogs, SVN_DISCOVER_CHANGED_PATHS);
        #Look at each log [msg] to see if it has a SAK in it
        $this->authors = array();
        foreach ($logs as $log) {
            preg_match_all("/(UMICH|KNL|SAM|SAK|EVALSYS|ASSN).\d+/",$log['msg'], $matches);
	    foreach ($log['paths'] as $path) {
		    if ($this->ignoremsub == true && strstr($path['path'],"msub")) {
			    continue 2;
		    }
	    }
            $retval[$log['rev']]['sak'] = $matches[0];
            //Need to convert /n to <br>
            $message = nl2br($log['msg']);
            $retval[$log['rev']]['msg'] = $message;
            array_push($this->authors,$log['author']);
            #Maybe return other things?
        }

        $total_logs=count($this->authors);
        print " Found $total_logs items";
        $this->cache->store("$url-$startrev-$endrev",$retval,$this->cachetime);
        return $retval;
    }

    #Gets the XML Jira for a SAK
    function getJira($sak) {
        if (!$sak) {
            return "";
        }
        $jira = "http://jira.sakaiproject.org/si/jira.issueviews:issue-xml";
        #Do some validation on $sak variable or a try/catch?
	
        if ($cacheval = $this->cache->fetch("JIRA::$sak")) {
            return $cacheval;
        }
        $contents = file_get_contents("$jira/$sak/$sak.xml");
        $this->cache->store("JIRA::$sak", $contents,$this->cachetime);
        return $contents;
    }


    //Display the SAK in various formats, tabular, rwiki, confluence

    function toRWIKITable($data,$header="") {

        //Style macro has some bug at the moment?
//        $ret="{style}.security{color:red;}{style}{table}";
        $ret="{table}";
        if (is_array($header)) {
            foreach ($header as $head) {
                $ret.="$head|";
            }
            $ret.="<br>";
        }

        if (is_array($data)) {
            foreach ($data as $row) {
                //Has it doesn't have multiple rows just make it have multiple rows
                if (!is_array($row)) {
                    $row = array($row);
                }
                foreach ($row as $col) {
                    //Clean up some data for rwiki:
                    //Replace <hr> with something wiki likes
                    $wikihr = '\=-\=-\=-\=-\=-\=-\=-\=';
                    $col=str_replace("<hr>","\\\\ $wikihr \\\\",$col);
                    #Bars are a column separator
                    $col=str_replace("|", '\|',$col);
                    #-- is a strike!
                    $col=str_replace("--", '-',$col);
                    #_ is a bold
                    $col=str_replace("__", '_',$col);

                    $col=preg_replace("/-{4,}/","$wikihr",$col);
                    $col=preg_replace("/<br.*?>/","\\\\\\\\",$col);
                    //Replace the security span
                    $col=str_replace("<span class='security'>","{span:security}",$col);
                    $col=str_replace("</span>","{span}",$col);

                    //Replace link (a href) with link
                    //http://www.webdeveloper.com/forum/showthread.php?t=190761
                    $col = preg_replace("#\<a.+href\=[\"|\'](.+)[\"|\'].*\>(.*)\<\/a\>#U","{link:$2|$1}",$col); 
                    //Fix links like [] that are SAK's
                    $col = preg_replace("/\[((?:UMICH|SAK|SAM|KNL|EVALSYS|ASSN)-\d+)\]/"," {link:$1|http://jira.sakaiproject.org/browse/$1} ",$col);
                    //Remove images for now, needs to be stored in the local system to be displayed
                    $col=preg_replace("/<img (.*)>/", "",$col);
                    $ret.="$col|";
                }
                $ret.="<br>";
            }
        }
        $ret.="{table}";
        return $ret;
    }

    function genSummary() {
        #Some statistics perhaps in a separate function? 
        $ret= "<br>Commits by author:<br>";
        $authors = array_count_values($this->authors);
        arsort($authors);
        foreach ($authors as $key=>$value) {
            $ret.= "$key $value<br>";
        }

        $ret.= "<br>Types of request:<br>";
        $types = array_count_values($this->types);
        arsort($types);
        foreach ($types as $key=>$value) {
            $ret.= "$key $value<br>";
        }
        return $ret;
    }

    function toHTMLTable($data,$header="") {
        $classes = array("even", "odd");

        $ret="<table border=1 class='sample'>";
        if (is_array($header)) {
            $ret.="<tr>";
            foreach ($header as $head) {
                $ret.="<th>$head</th>";
            }
            $ret.="</tr>";
        }
        if (is_array($data)) {
            foreach ($data as $row) {
                //Has it doesn't have multiple rows just make it have multiple rows
                if (!is_array($row)) {
                    $row = array($row);
                }
                $class = next($classes) or reset($classes);
                $ret.="<tr class=$class>";
                foreach ($row as $col) {
                    $ret.="<td>$col</td>";
                }
                $ret.="</tr>";
            }
        }
        $ret.="</table>";
        return $ret;
    }

    #Returns a jira style href to a sak
    function jiraLink($sak) {
        return "<a target='jira' href='http://jira.sakaiproject.org/browse/$sak'>$sak</a>";
    }

    function revisionLink($rev) {
        return "<a target='revision' href='https://source.sakaiproject.org/viewsvn?view=revision&revision=$rev'>$rev</a>";
    }

    function displaySAKTable($url,$startrev,$endrev,$type="html") {
        $saks = $this->getSAKS($url,$startrev,$endrev);
        $data=array();
        $this->types = array();
        foreach ($saks as $revision => $sak) {
            $jira = $this->getJira($sak['sak'][0]);
            //Try catch needed here
            if (strpos($jira,"<html>")!==FALSE) {
                $item = array($this->revisionLink($revision),"<span class='security'>Security issue<br>".$this->jiraLink($sak['sak'][0])."</span>",$sak['msg']);

            }
            else if($jira) {
                $xml = new SimpleXMLElement($jira);
                $version=xmlimplode(" ",$xml->channel->item->fixVersion);
                if (!$version) {
                    $version="None";
                }
                //Search through comments looking for "tested, verified, etc"
                $verified="";
                if ($xml->channel->item->comments->comment) {
                    foreach ($xml->channel->item->comments->comment as $comment) {
                        if (preg_match("/(verified|tested|checked)/i",$comment[0])) {
                            $verified.="{$comment[0]} by <a href='http://jira.sakaiproject.org/secure/ViewProfile.jspa?name={$comment["author"]}'>{$comment["author"]}</a><hr>";
                        }
                    }
                }
                if (!$verified) {
                    $verified="No verification message found.";
                }
                $priority = $this->genPriority($xml->channel->item->priority);
                $status = $this->genStatus($xml->channel->item->status);
                $type = $this->genStatus($xml->channel->item->type);
                array_push($this->types, (string) $xml->channel->item->type);
                $branchStatus = $this->genBranchStatus($xml->channel->item->customfields);
                $item = array($this->revisionLink($revision),$this->jiraLink($sak['sak'][0]),$sak['msg'],$xml->channel->item->title,$status,$type,$branchStatus,$priority,$version,$verified);
            }
            else {
                $item = array($this->revisionLink($revision),"<b>No SAK Detected</b>",$sak['msg']);
            }
            array_push($data,$item);
        }
        $header = array("Revision", "Detected SAK Number(s)","SVN Message","Title","Overall Status","Type","Branch Status", "Priority","Fix Version(s)","Verified");
        if ($type=="rwiki") {
            return $this->toRWIKITable($data,$header);
        } else {
            //Default is html
            return $this->toHTMLTable($data, $header);
        }
    }

    //Will color the priority
    function genPriority($priority) {
        return "<img src={$priority['iconUrl']}>$priority";
    }
    //Return status with the icon
    function genStatus($status) {
        return "<img src={$status['iconUrl']}>$status";
    }

    //Parse through 'custom fields' looking for branch status
    function genBranchStatus($customFields) {
        foreach ($customFields->customfield as $custom) {
            if (preg_match("/\d\.\d\.x.*/",$custom->customfieldname)) {
//                var_dump($custom);
//                print $custom->customfieldname.":".$custom->customfieldvalue;
            }
        }
    }
}

//These will be where the form is pulled from
gentime();
$saksummary = new SakSummary(3600);
//print $saksummary->displaySAKTable("https://source.sakaiproject.org/svn/site-manage/branches/sakai_2-6-x",66560,69901);

if ($sr && $er && $tag) {
    $table = $saksummary->displaySAKTable("https://source.sakaiproject.org/$tag",$sr,$er, $format);
    $summary = $saksummary->genSummary();
    print $summary."<hr>";
    print $table;
}
print "<hr>Your page generated in ".gentime()." seconds";

?>
</body>
</html>
