{extends 'user/base.php'}
{block name="content"}
<div class="row">
  <div class="col-md-6 grid-margin stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Profile</h4>
        <form class="forms-sample" action="" method="post">
          <div class="form-group">
            <label for="exampleInputName1">Name</label>
            <input type="text" class="form-control" id="exampleInputName1" name="username" placeholder="Name" value="{$data.username}">
          </div>
          <div class="form-group">
            <label for="exampleInputEmail3">Email address</label>
            <input type="email" class="form-control" id="exampleInputEmail3" name="useremail" placeholder="Email" value="{$data.useremail}">
          </div>
          <div class="form-group">
            <label for="exampleInputPassword4">Password</label>
            <input type="password" class="form-control" id="exampleInputPassword4" name="password" placeholder="Leave empty for retain password">
          </div>
          <button type="submit" class="btn btn-success mr-2">Submit</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-6 stretch-card">
    <div class="card">
      <div class="card-body">
        <h4 class="card-title">Upload Avatar</h4>
        <p class="card-description">
            <div class="profile-image">
              <img src="/user/avatar/{cookie('user_id')}.png" onerror="this.src='https://www.gravatar.com/avatar/{cookie('user_useremail')}?d=robohash';" alt="image" width="200"/> 
            </div>
        </p>
        <form class="forms-sample" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <div class="input-group col-xs-12">
              <input type="file" class="form-control file-upload-info" name="avatar" placeholder="Upload Image">
            </div>
          </div>
          <button type="submit" class="btn btn-success mr-2">Submit</button>
        </form>
      </div>
    </div>
  </div>
</div>
{/block}