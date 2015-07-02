{if is_set( $script_id )}
<div class="message-feedback">
    <h2>Your import upload is being processed</h2>
    <p>{* Results will be emailed to {$email} when complete.*} You can monitor its progress <a href={concat( '/scriptmonitor/view/', $script_id )|ezurl()}>here</a>.</p>
</div>
{/if}

<h2>Import Content Objects</h2>
<form method='POST' enctype='multipart/form-data' action=''>
    {def $classes=fetch( 'class', 'list' )}
    <p>First, Select a class of objects to import:</p>
    <select name="class_identifier">
        {foreach $classes as $class}
        <option value="{$class.identifier}">{$class.name}</option>
        {/foreach}
    </select>
    <br />
    <p><lable for='importcsvfile'>Next, select a CSV file of content object data to upload and import&nbsp;</label></p>
    <input name='importcsvfile' type='file'/>
    <br />
    <br />
    <input name='submit' type='submit' value='Upload'/>
</form>

{* no longer relevant as this information now goes in an email once the script has completed
{if $error_string|ne('vital')}<p>{$error_string}</p>{/if}
{if $error_string|contains('vital')|not}
<p>Successful import.</p>
{/if}
*}