{* Add an "BCImportCsv : Import Content Objects" item in Subitems context menu *}
<hr/>
<script type="text/javascript">
<!--
menuArray['SubitemsContextMenu']['elements']['child-menu-uiupload'] = new Array();
menuArray['SubitemsContextMenu']['elements']['child-menu-uiupload']['url'] = {"/bcimportcsv/upload/%nodeID%"|ezurl};
// menuArray['SubitemsContextMenu']['elements']['child-menu-uidownload'] = new Array();
// menuArray['SubitemsContextMenu']['elements']['child-menu-uidownload']['url'] = {"/bcimportcsv/download/%nodeID%"|ezurl};
// -->
</script>

<a id="child-menu-uiupload" href="#" onmouseover="ezpopmenu_mouseOver( 'ContextMenu' )" >Import Content Objects</a>
{* <a id="child-menu-uidownload" href="#" onmouseover="ezpopmenu_mouseOver( 'ContextMenu' )">Download user data</a> *}
