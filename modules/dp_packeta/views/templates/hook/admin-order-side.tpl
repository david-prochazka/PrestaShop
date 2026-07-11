{**
 * Packeta Delivery Methods — panel on the back office order page
 * (hook displayAdminOrderSide). MIT licensed.
 *}
<div class="card mt-2" id="dp-packeta-order-panel">
  <div class="card-header">
    <h3 class="card-header-title">Packeta</h3>
  </div>
  <div class="card-body">
    {if $dp_packeta_order.is_pickup && $dp_packeta_order.selection}
      <p class="mb-2">
        <strong>{l s='Pickup point' d='Modules.Dppacketa.Admin'}:</strong><br>
        {$dp_packeta_order.selection.point_name|escape:'html':'UTF-8'}<br>
        {$dp_packeta_order.selection.street|escape:'html':'UTF-8'},
        {$dp_packeta_order.selection.zip|escape:'html':'UTF-8'} {$dp_packeta_order.selection.city|escape:'html':'UTF-8'}
        ({$dp_packeta_order.selection.country|upper|escape:'html':'UTF-8'})<br>
        <small class="text-muted">
          {l s='Point ID' d='Modules.Dppacketa.Admin'}: {$dp_packeta_order.selection.point_id|escape:'html':'UTF-8'}
          {if $dp_packeta_order.selection.carrier_pickup_point}
            / {$dp_packeta_order.selection.carrier_pickup_point|escape:'html':'UTF-8'}
          {/if}
        </small>
      </p>
    {else}
      <p class="mb-2">
        {l s='Home delivery via Packeta carrier' d='Modules.Dppacketa.Admin'}
        <code>{$dp_packeta_order.packeta_carrier_id|escape:'html':'UTF-8'}</code>
      </p>
    {/if}

    {if $dp_packeta_order.packet_id}
      <p class="mb-2">
        <strong>{l s='Packet' d='Modules.Dppacketa.Admin'}:</strong>
        <a href="{$dp_packeta_order.tracking_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {$dp_packeta_order.packet_barcode|escape:'html':'UTF-8'}
        </a>
      </p>
      {if $dp_packeta_order.label_url}
        <a class="btn btn-outline-secondary btn-sm" href="{$dp_packeta_order.label_url|escape:'html':'UTF-8'}">
          <i class="material-icons">print</i>
          {l s='Download label' d='Modules.Dppacketa.Admin'}
        </a>
      {/if}
    {elseif $dp_packeta_order.can_export && $dp_packeta_order.export_url}
      <form method="post" action="{$dp_packeta_order.export_url|escape:'html':'UTF-8'}">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="material-icons">local_shipping</i>
          {l s='Export to Packeta' d='Modules.Dppacketa.Admin'}
        </button>
      </form>
    {else}
      <p class="text-muted mb-0">
        {l s='Set the API password in the Packeta module settings to export packets.' d='Modules.Dppacketa.Admin'}
      </p>
    {/if}
  </div>
</div>
