{* Add an "Upload User Data" item in Admin Content tree context menu*}
<hr/>
<script type="text/javascript">
<!--

menuArray['ClassMenu']['elements']['menu-uiclassupload'] = {ldelim} 'url': {"/bcimportcsv/upload/%nodeID%"|ezurl} {rdelim};
// menuArray['ClassMenu']['elements']['menu-uiclassdownload'] = {ldelim} 'url': {"/bcimportcsv/download/%nodeID%"|ezurl} {rdelim};

// -->
</script>

<a id="menu-uiclassupload" onmouseover="return false" href="#">Import Content Objects</a>
{* <a id="menu-uiclassdownload" onmouseover="return false" href="#">Download user data</a> *}