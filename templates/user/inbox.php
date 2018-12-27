{extends 'user/base.php'}
{block name="content"}
<div class="row">
<div class="col-lg-6 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Inbox</h4>
      <table class="table">
        <thead>
          <tr>
            <th width="60%">Subject</th>
            <th width="20%">From</th>
            <th width="20%">Action</th>
          </tr>
        </thead>
        <tbody>
          {foreach $inbox as $d}
          <tr>
            <td>{$d.subject}</td>
            <td>{$d.user_id|getUsernameById}</td>
            <td>
              <a href="/user/inbox.php?view_id={$d.id}" class="btn btn-warning font-weight-medium">View</a>
              <a href="/user/inbox.php?delete=1&id={$d.id}" class="btn btn-warning font-weight-medium">Delete</a></td>
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
<div class="col-lg-6 grid-margin stretch-card">
  <div class="card">
    <div class="card-body">
      <h4 class="card-title">Sent</h4>
      <table class="table">
        <thead>
          <tr>
            <th width="60%">Subject</th>
            <th width="20%">To</th>
            <th width="20%">Action</th>
          </tr>
        </thead>
        <tbody>
          {foreach $sent as $d}
          <tr>
            <td>{$d.subject}</td>
            <td>{$d.to_id|getUsernameById}</td>
            <td>
              <a href="/user/inbox.php?view_id={$d.id}" class="btn btn-warning font-weight-medium">View</a>
              <a href="/user/inbox.php?delete=1&id={$d.id}" class="btn btn-warning font-weight-medium">Delete</a></td>
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

<div class="row">
  {if !empty($msg)}
  <div class="col-6 stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">View Message</h4>
        <p class="card-description"><b>From</b>: {$msg.from}</p>
        <p class="card-description"><b>To</b>: {$msg.to}</p>
        <p class="card-text"><b>Message</b>:<br />{$msg.message}</p>
        <p class="card-text"><small class="text-muted">Sent at {$msg.created_at}</small></p>
      </div>
    </div>
  </div>
  {/if}
  <div class="col-6 grid-margin stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Send Message</h4>
        <p class="card-description">
        </p>
        <form class="forms-sample" method="post" action="">
          <div class="form-group row">
            <label for="exampleInputPassword2" class="col-sm-3 col-form-label">To</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" id="exampleInputPassword2" name="to" placeholder="User">
            </div>
          </div>
          <div class="form-group row">
            <label for="exampleInputPassword2" class="col-sm-3 col-form-label">Subject</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" id="exampleInputPassword2" name="subject" placeholder="Subject">
            </div>
          </div>
          <div class="form-group">
            <label for="exampleTextarea1">Message</label>
            <textarea class="form-control" id="exampleTextarea1" name="message" rows="4"></textarea>
          </div>
          <button type="submit" class="btn btn-success mr-2">Send</button>
        </form>
      </div>
    </div>
  </div>
</div>
{/block}