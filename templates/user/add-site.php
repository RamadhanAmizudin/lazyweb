{extends 'user/base.php'}
{block name="content"}
<div class="row">
<div class="col-lg-6 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">List Sites</h4>
      <table class="table">
        <thead>
          <tr>
            <th>Sites</th>
            <th>Manage</th>
          </tr>
        </thead>
        <tbody>
          {foreach $data.sites as $site}
          <tr>
            <td>{$site.url}</td>
            <td>
              <a href="add-site.php?ping=1&id={$site.id}" class="btn btn-block btn-warning btn-sm font-weight-medium">Ping</a>
              <a href="add-site.php?delete=1&id={$site.id}" class="btn btn-block btn-warning btn-sm font-weight-medium">Delete</a>
            </td>
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
<div class="col-6 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Add Site Form</h4>
      <p class="card-description">
      </p>
      <form class="forms-sample" method="post" action="">
        <div class="form-group row">
          <label for="exampleInputPassword2" class="col-sm-3 col-form-label">URL</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="exampleInputPassword2" name="url" placeholder="URL">
          </div>
        </div>
        <button type="submit" class="btn btn-success mr-2">Submit</button>
      </form>
    </div>
  </div>
</div>
</div>
{if !empty($cmdout)}
<div class="row">
  <div class="col-6 grid-margin stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Ping Result</h4>
        <p class="card-description">
        </p>
        <div class="well">{$cmdout}</div>
      </div>
    </div>
  </div>
</div>
{/if}
{/block}