<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>LazyStatus | Registration</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="/user/node_modules/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="/user/node_modules/simple-line-icons/css/simple-line-icons.css">
  <!-- endinject -->
  <!-- plugin css for this page -->
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="/user/css/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="/user/images/favicon.png" />
</head>

<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth register-full-bg">
        <div class="row w-100">
          <div class="col-lg-4 mx-auto">
            <div class="auth-form-light text-left p-5">
              <h2>Register</h2>
              {if !empty($error)}
              <h4 class="font-weight-light text-danger">{$error}</h4>
              {/if}
              <form class="pt-4" action="" method="POST">
                  <div class="form-group">
                    <label for="exampleInputEmail1">User Email</label>
                    <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" name="usermail" placeholder="User Email">
                    <i class="mdi mdi-account"></i>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputEmail1">User Name</label>
                    <input type="text" class="form-control" id="exampleInputEmail1" aria-describedby="emalHelp" name="username" placeholder="User Name">
                    <i class="mdi mdi-account"></i>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputPassword1">Password</label>
                    <input type="password" class="form-control" id="exampleInputPassword1" name="p1" placeholder="Password">
                    <i class="mdi mdi-eye"></i>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputPassword2">Password</label>
                    <input type="password" class="form-control" id="exampleInputPassword2" name="p2" placeholder="Confirm password">
                    <i class="mdi mdi-eye"></i>
                  </div>
                  <div class="mt-5">
                    <input type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium" value="Register">
                  </div>
                  <div class="mt-2 w-75 mx-auto">
                    <div class="form-check form-check-flat">
                      <label class="form-check-label">
                        <input type="checkbox" class="form-check-input">
                        I accept terms and conditions
                      </label>
                    </div>
                  </div>
                  <div class="mt-2 text-center">
                    <a href="login.php" class="auth-link text-black">Already have an account? <span class="font-weight-medium">Sign in</span></a>
                  </div>           
              </form>
            </div>
          </div>
        </div>
      </div>
      <!-- content-wrapper ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->
  <!-- plugins:js -->
  <script src="/user/node_modules/jquery/dist/jquery.min.js"></script>
  <script src="/user/node_modules/popper.js/dist/umd/popper.min.js"></script>
  <script src="/user/node_modules/bootstrap/dist/js/bootstrap.min.js"></script>
  <!-- endinject -->
  <!-- inject:js -->
  <script src="/user/js/off-canvas.js"></script>
  <script src="/user/js/misc.js"></script>
  <!-- endinject -->
</body>

</html>
