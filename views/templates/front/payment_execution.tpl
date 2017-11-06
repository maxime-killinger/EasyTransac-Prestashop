{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}"
       title="{l s='Go back to the Checkout' mod='easytransac'}">{l s='Checkout' mod='easytransac'}</a>
    <span class="navigation-pipe"></span>
    {l s='EasyTransac' mod='easytransac'}
{/capture}


<h2>{l s='Order summary' mod='easytransac'}</h2>

{assign var='current_step' value='payment'}

{if $nbProducts <= 0}
<p class="warning">{l s='Your shopping cart is empty.' mod='easytransac'}</p>
{elseif $payment_page_url}

<div class="box cheque-box">

    <h3 class="page-subheading">EasyTransac</h3>
    <form action="{$payment_page_url}" method="get" id="easytransac_payment_form">
        <img src="/modules/easytransac/views/img/icon.jpg" alt="{l s='EasyTransac' mod='easytransac'}" height="49"
             style="float:left; margin: 0px 10px 5px 0px;"/>
        <div style="margin-left: 120px;">
            <p>
                {l s='You have chosen to pay with EasyTransac.' mod='easytransac'}
                <br/><br/>
                {l s='Here is a short summary of your order:' mod='easytransac'}
            </p>
            <p style="margin-top:10px;">
                - {l s='The total amount of your order is' mod='easytransac'}
            </p>
            <p style="margin-top:10px;">
                {l s='You\'ll be redirected to EasyTransac on the next page.' mod='easytransac'}
                <br/><br/>
            </p>
        </div>

        {*			<p class="cart_navigation clearfix" id="cart_navigation">
                        <a class="button-exclusive btn btn-default" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}">
                            <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='easytransac'}
                        </a>
                        <button class="button btn btn-default button-medium" type="submit">
                            <span>{l s='Pay with EasyTransac' mod='easytransac'}<i class="icon-chevron-right right"></i></span>
                        </button>
                    </p>*}
    </form>
    <script type="text/javascript">
        document.getElementById('easytransac_payment_form').submit();
    </script>

    {else}
    <p class="warning"><b>{l s='Please try to contact the merchant:' mod='easytransac'}</b></p>

    <p class="cart_navigation" id="cart_navigation">
        <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}"
           class="button_large">{l s='Other payment methods' mod='easytransac'}</a>
    </p>

    {/if}

</div>