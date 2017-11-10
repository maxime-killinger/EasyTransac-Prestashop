
{capture name=path}
	<a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='easytransac'}">{l s='Checkout' mod='easytransac'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='EasyTransac' mod='easytransac'}
{/capture}



<h2>{l s='Order summary' mod='easytransac'}</h2>

{assign var='current_step' value='payment'}
{include file="module:ps_shoppingcart/ps_shoppingcart-product-line.tpl"}


<div class="box cheque-box">

	<h3 class="page-subheading">EasyTransac</h3>

	<p>
		{l s='Your order on' mod='easytransac'} <span class="bold">{$shop_name|escape:'htmlall':'UTF-8'}</span> {l s='is currently being processed.' mod='easytransac'}
		<br /><br />
	<div class="easytproclo"></div>
	<img src="/modules/easytransac/views/img/loader.gif" height="46px" width="46px"/>
	{l s='Please wait for EasyTransac payment confirmation...' mod='easytransac'}

	{literal}
		<script>
			setTimeout(function () {
				location.reload();
			}, 5000);
		</script>
	{/literal}
	</p>
</div>



