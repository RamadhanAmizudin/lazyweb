{extends 'user/base.php'}
{block name="content"}
<div class="row">
<div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 grid-margin stretch-card">
  <div class="card card-statistics">
    <div class="card-body">
      <div class="clearfix">
        <div class="float-left">
          <i class="mdi mdi-cube text-danger icon-lg"></i>
        </div>
        <div class="float-right">
          <p class="card-text text-right">Total Sites</p>
          <div class="fluid-container">
            <h3 class="card-title font-weight-bold text-right mb-0">{$data.total_sites}</h3>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 grid-margin stretch-card">
  <div class="card card-statistics">
    <div class="card-body">
      <div class="clearfix">
        <div class="float-left">
          <i class="mdi mdi-receipt text-warning icon-lg"></i>
        </div>
        <div class="float-right">
          <p class="card-text text-right">Orders</p>
          <div class="fluid-container">
            <h3 class="card-title font-weight-bold text-right mb-0">3455</h3>
          </div>
        </div>
      </div>
      <p class="text-muted mt-3">
        <i class="mdi mdi-bookmark-outline mr-1" aria-hidden="true"></i> Product-wise sales
      </p>
    </div>
  </div>
</div>
<div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 grid-margin stretch-card">
  <div class="card card-statistics">
    <div class="card-body">
      <div class="clearfix">
        <div class="float-left">
          <i class="mdi mdi-poll-box text-teal icon-lg"></i>
        </div>
        <div class="float-right">
          <p class="card-text text-right">Sales</p>
          <div class="fluid-container">
            <h3 class="card-title font-weight-bold text-right mb-0">5693</h3>
          </div>
        </div>
      </div>
      <p class="text-muted mt-3">
        <i class="mdi mdi-calendar mr-1" aria-hidden="true"></i> Weekly Sales
      </p>
    </div>
  </div>
</div>
<div class="col-xl-3 col-lg-3 col-md-3 col-sm-6 grid-margin stretch-card">
  <div class="card card-statistics">
    <div class="card-body">
      <div class="clearfix">
        <div class="float-left">
          <i class="mdi mdi-account-location text-info icon-lg"></i>
        </div>
        <div class="float-right">
          <p class="card-text text-right">Employees</p>
          <div class="fluid-container">
            <h3 class="card-title font-weight-bold text-right mb-0">246</h3>
          </div>
        </div>
      </div>
      <p class="text-muted mt-3">
        <i class="mdi mdi-reload mr-1" aria-hidden="true"></i> Product-wise sales
      </p>
    </div>
  </div>
</div>
</div>
<div class="row">
<div class="col-lg-12 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">List Sites</h4>
      <table class="table">
        <thead>
          <tr>
            <th>Sites</th>
          </tr>
        </thead>
        <tbody>
          {foreach $data.sites as $site}
          <tr>
            <td>{$site.url}</td>
          </tr>
          {foreachelse}
          <tr>
            <td><i>No sites</i></td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>
{/block}