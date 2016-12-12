
{if $smarty.const._PS_VERSION_ >= 1.6}

	<div class="row">
		<div class="col-xs-12 col-md-6">
			<p class="payment_module easytransac">
				<a href="{$link->getModuleLink('easytransac', 'payment')|escape:'html'}" title="{l s='Pay with EasyTransac' mod='easytransac'}">
					<img src="{$base_dir_ssl|escape:'htmlall':'UTF-8'}modules/easytransac/views/img/icon.jpg" />
					{l s='Pay with EasyTransac' mod='easytransac'}

				</a>
			</p>
		</div>
	</div>

{else}
	<p class="payment_module easytransac">
		<a href="{$link->getModuleLink('easytransac', 'payment')|escape:'html'}" title="{l s='Pay with EasyTransac' mod='easytransac'}">
			<img src="{$base_dir_ssl}modules/easytransac/views/img/icon.jpg" />
			{l s='Pay with EasyTransac' mod='easytransac'}
		</a>
	</p>

{/if}

<style>
	p.payment_module.easytransac a 
	{ldelim}
	padding-left:17px;
	{rdelim}
</style>