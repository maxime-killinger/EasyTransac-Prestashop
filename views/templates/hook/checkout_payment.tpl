{if $smarty.const._PS_VERSION_ >= 1.6}

	<div class="row">
		<div class="col-xs-12 col-md-6">
			<p class="payment_module easytransac">
					{l s='Pay with EasyTransac' mod='easytransac'}
                                        
			</p>
                        <div id="easytransac-namespace" style="margin-left: 20px;height:100px;">
                        </div>
                        <script type="text/javascript" src="{if isset($force_ssl) && $force_ssl}{$base_dir_ssl}{else}{$base_dir}{/if}/modules/easytransac/views/js/oneclick.js"></script>
		</div>
	</div>

{else}
	<p class="payment_module easytransac">
			{l s='Pay with EasyTransac' mod='easytransac'}
	</p>
{/if}

<style>
	p.payment_module.easytransac a 
	{ldelim}
	padding-left:17px;
	{rdelim}
</style>