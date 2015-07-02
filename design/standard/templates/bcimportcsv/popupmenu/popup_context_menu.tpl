{* Add an "BCImportCsv : Import Content Objects" item in Admin Content tree context menu*}
<hr/>
<script type="text/javascript">
<!--
menuArray['ContextMenu']['elements']['menu-uiupload'] = new Array();
menuArray['ContextMenu']['elements']['menu-uiupload']['url'] = {"/bcimportcsv/upload/%nodeID%"|ezurl};
// menuArray['ContextMenu']['elements']['menu-uidownload'] = new Array();
// menuArray['ContextMenu']['elements']['menu-uidownload']['url'] = {"/bcimportcsv/download/%nodeID%"|ezurl};

// -->
</script>

<a id="menu-uiupload" href="#" onmouseover="ezpopmenu_mouseOver( 'ContextMenu' )">Import Content Objects</a>
{* <a id="menu-uidownload" href="#" onmouseover="ezpopmenu_mouseOver( 'ContextMenu' )">Download user data</a> *}
