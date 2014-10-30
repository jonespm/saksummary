<html>

<script>
function removeHTTP(str) {
	var n = str.indexOf("/svn/");
	if (n != -1) {
		return (str.substr(n+4,n.length));
	}
	return str;
}

function quickSVN() {
	url = encodeURIComponent("/"+removeHTTP(document.getElementById('qtool').value));
	revision = document.getElementById('qrevision').value;
	redirect = "https://source.sakaiproject.org/websvn/revision.php?repname=sakai-svn&path="+url+"&rev="+revision;
	window.open(redirect,'newwindow');
}

function blameSVN() {
	url = encodeURIComponent("/"+removeHTTP(document.getElementById('btool').value));
	revision = document.getElementById('brevision').value;
	redirect = "https://source.sakaiproject.org/websvn/blame.php?repname=sakai-svn&path="+url+"&rev="+revision;
	window.open(redirect,'newwindow');
}

</script>
Quick Revision! Put in a Sakai Tool (path) and a revision (optional)!
<br>
Tool: <input id="qtool"type=text>
Revision: <input id="qrevision" type=text>
<button id="submit" name="submit" type="submit" value="Submit" onclick="quickSVN();">Submit</button>

<br>
Quick Blame! Put in a file and revision (optional)!
<br>
File Path: <input id="btool"type=text>
Revision: <input id="brevision" type=text>
<button id="submit" name="submit" type="submit" value="Submit" onclick="blameSVN();">Submit</button>
</html>
