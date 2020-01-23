{if $errorMessage}
  <div class="messages status no-popup">
    <span class="icon inform-icon"></span>{ts}{$errorMessage}{/ts}
  </div>
{else}
  <table id="selectedContributionRecords" class="selector">
    <thead >
      <tr>
        <th>{ts}Name{/ts}</th>
        <th>{ts}Amount{/ts}</th>
        <th>{ts}Type{/ts}</th>
        <th>{ts}Source {/ts}</th>
        <th>{ts}Received{/ts}</th>
      </tr>
    </thead>
    {foreach from=$contributionsRows item=row}
      <tr>
        <td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}" target="_blank">{$row.display_name}</a></td>
        <td>{$row.total_amount}</td>
        <td>{$row.financial_account}</td>
        <td>{$row.source}</td>
        <td>{$row.receive_date}</td>
      </tr>
    {/foreach}
  </table>
{/if}

{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      //load jQuery data table.
      $('#selectedContributionRecords').dataTable( {
        "sPaginationType": "full_numbers",
        "bJQueryUI"  : true,
        "bFilter"    : false
      });
    });
  </script>
{/literal}