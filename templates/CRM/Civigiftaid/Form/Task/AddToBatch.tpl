{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                              |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}

{crmStyle ext=uk.co.compucorp.civicrm.giftaid file=resources/css/dist.css}
{crmScript ext=uk.co.compucorp.civicrm.giftaid file=resources/js/script.js}

<div id="gift-aid-add" class="crm-block crm-form-block crm-export-form-block gift-aid">
    <h2>{ts}Add To Gift Aid{/ts}</h2>

    <div class="help"><p>{ts}Use this form to submit Gift Aid contributions.{/ts}</p></div>

    <table class="form-layout">
        <tr>
            <td>
                <table class="form-layout">
                    <tr>
                        <td class="label">{$form.title.label}</td>
                        <td>{$form.title.html}</td>
                    <tr>
                    <tr>
                        <td class="label">{$form.description.label}</td>
                        <td>{$form.description.html}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <h3>{ts}Summary{/ts}</h3>

    <table class="report" style="width: 100%">
      <tbody>
        <tr class="columnheader-dark">
          <th></th>
          <th></th>
          <th></th>
        </tr>
        <tr>
          <td><strong>Number of selected contributions:</strong></td>
          <td colspan="2"><strong>{$selectedContributions}</strong></td>
        </tr>
        <tr>
          <td><strong>Number of contributions that will be added to this batch:</strong></td>
          <td>{$totalAddedContributions}</td>
          <td align="center">
            {if $totalAddedContributions}
              <a class="crm-popup" href="{$contributionsTobeAddedUrl}" title="To be added to this batch">{ts}view contributions{/ts}</a>
            {/if}
          </td>
        </tr>
        <tr>
          <td><strong>Number of contributions already in a batch:</strong></td>
          <td>{$alreadyAddedContributions}</td>
          <td align="center">
            {if $alreadyAddedContributions}
              <a class="crm-popup" href="{$contributionsAlreadyAddedUrl}" title="Already in a batch">{ts}view contributions{/ts}</a>
            {/if}
          </td>
        </tr>
        <tr>
          <td><strong>Number of contributions not valid for gift aid:</strong></td>
          <td>{$notValidContributions}</td>
          <td align="center">
            {if $notValidContributions}
              <a class="crm-popup" href="{$contributionsInvalidUrl}" title="Not Valid for Giftaids">{ts}view contributions{/ts}</a>
            {/if}
          </td>
        </tr>
      </tbody>
    </table>

    <p>{ts}Use this form to submit Gift Aid contributions. Note that this action is irreversible, i.e. you cannot take contributions out of a batch once they have been added.{/ts}</p>

    <p><strong>Possible reasons for contributions not valid for gift aid:</strong></p>

    <ol>
        <li>Contribution status is not 'Completed'</li>
        <li>Related Contact does not have a valid gift aid declaration</li>
        <li>Related Contact's gift aid declaration does not cover the contribution date</li>
    </ol>

    {$form.buttons.html}
</div>
