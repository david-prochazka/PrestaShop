{**
 * One Page Checkout — replaces checkout/checkout-process.tpl.
 *
 * Renders every native checkout step inside a grid of panels. The steps
 * themselves are still rendered by the active theme's step templates, so
 * theme customizations and module hooks inside steps keep working.
 *
 * @author    Integritty
 * @copyright 2026 Integritty
 * @license   https://opensource.org/licenses/MIT MIT License
 *}
<div id="opc-wrapper"
     class="opc opc-layout-{$opc.layout|escape:'htmlall':'UTF-8'}{if $opc.ajax} opc-ajax{/if}{if $opc.sticky_summary} opc-sticky-summary{/if}"
     data-opc-url="{$urls.pages.order|escape:'htmlall':'UTF-8'}">
  {foreach from=$steps item="step" key="index"}
    <div class="opc-panel{if !$step.is_reachable} opc-panel--locked{/if}{if $step.is_complete} opc-panel--complete{/if}{if $step.is_current} opc-panel--current{/if}"
         data-opc-step="{$step.identifier|escape:'htmlall':'UTF-8'}"
         data-opc-position="{$index + 1}">
      {$step.ui->render(['position' => $index + 1]) nofilter}
      {if !$step.is_reachable}
        <p class="opc-panel__locked-note">
          <i class="material-icons" aria-hidden="true">lock</i>
          {l s='Complete the previous steps to unlock this section.' d='Modules.Onepagecheckout.Shop'}
        </p>
      {/if}
    </div>
  {/foreach}
</div>
