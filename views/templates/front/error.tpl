{extends file='page.tpl'}

{block name='page_content'}
  <div class="row">
    <div class="col-xs-12">
      <div class="alert alert-danger">
        <h4>{l s='Order Failed' d='Shop.Theme.Customeraccount'}</h4>
		<p>{l s='We are sorry, but your payment was successful but not a valid order. Please try again later or contact support for assistance.' d='Shop.Theme.Customeraccount'}</p>
      </div>
    </div>
  </div>
{/block}