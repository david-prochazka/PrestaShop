{**
 * Packeta Delivery Methods — pickup point selector rendered under a linked
 * carrier in checkout (hook displayCarrierExtraContent). MIT licensed.
 *}
<div class="dp-packeta js-dp-packeta"
     data-id-carrier="{$dp_packeta.id_carrier|intval}"
     data-selected="{if $dp_packeta.selection}1{else}0{/if}"
     data-widget="{$dp_packeta.widget|escape:'html':'UTF-8'}">
  <button type="button" class="btn btn-secondary btn-sm dp-packeta__open js-dp-packeta-open">
    {if $dp_packeta.selection}
      {l s='Change pickup point' d='Modules.Dppacketa.Shop'}
    {else}
      {l s='Select a pickup point' d='Modules.Dppacketa.Shop'}
    {/if}
  </button>
  <span class="dp-packeta__selected js-dp-packeta-selected{if !$dp_packeta.selection} dp-packeta--hidden{/if}">
    {if $dp_packeta.selection}
      {$dp_packeta.selection.point_name|escape:'html':'UTF-8'},
      {$dp_packeta.selection.street|escape:'html':'UTF-8'},
      {$dp_packeta.selection.zip|escape:'html':'UTF-8'} {$dp_packeta.selection.city|escape:'html':'UTF-8'}
    {/if}
  </span>
  <span class="dp-packeta__error js-dp-packeta-error dp-packeta--hidden">
    {l s='Please select a pickup point to continue.' d='Modules.Dppacketa.Shop'}
  </span>
</div>
