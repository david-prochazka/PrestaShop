{**
 * Packeta Delivery Methods — selected pickup point shown on the order
 * confirmation page and customer order detail. MIT licensed.
 *}
<div class="card dp-packeta-order-point">
  <div class="card-block">
    <h4 class="h4">{l s='Packeta pickup point' d='Modules.Dppacketa.Shop'}</h4>
    <p class="mb-0">
      <strong>{$dp_packeta_selection.point_name|escape:'html':'UTF-8'}</strong><br>
      {$dp_packeta_selection.street|escape:'html':'UTF-8'}<br>
      {$dp_packeta_selection.zip|escape:'html':'UTF-8'} {$dp_packeta_selection.city|escape:'html':'UTF-8'}
      {if $dp_packeta_selection.country} ({$dp_packeta_selection.country|upper|escape:'html':'UTF-8'}){/if}
      {if $dp_packeta_selection.point_url}
        <br><a href="{$dp_packeta_selection.point_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {l s='Pickup point details' d='Modules.Dppacketa.Shop'}
        </a>
      {/if}
    </p>
  </div>
</div>
