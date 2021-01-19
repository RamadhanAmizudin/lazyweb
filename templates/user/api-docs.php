{extends 'user/base.php'}
{block name="content"}
<div class="row">
<div class="col-lg-6 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">List Sites</h4>
      <h4 class="card-description">Our API is still in alpha stage, currently support login mechanism only</h4>
      <p class="card-text"><b>Endpoint</b>: http://{$smarty.server.HTTP_HOST}/user/api.php</p>
      Data:
      <div class="card-block bg-light">
        {$xml|nl2br}
      </div>
    </div>
  </div>
</div>
</div>

{/block}
