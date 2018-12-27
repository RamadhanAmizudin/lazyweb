<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>LazyStatus | Login</title>
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
      <div class="content-wrapper d-flex align-items-center auth login-full-bg">
        <div class="row w-100">
          <div class="col-lg-4 mx-auto">
            <div class="auth-form-dark text-left p-5">
              <h2>Login</h2>
              {if !empty($error)}
              <h4 class="font-weight-light text-danger">{$error}</h4>
              {/if}
              <form class="pt-5" action="login.php" method="post">
                <form action="login.php" method="post">
                  <div class="form-group">
                    <label for="exampleInputEmail1">Email</label>
                    <input type="email" name="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" placeholder="Email">
                    <i class="mdi mdi-account"></i>
                  </div>
                  <div class="form-group">
                    <label for="exampleInputPassword1">Password</label>
                    <input type="password" name="password" class="form-control" id="exampleInputPassword1" placeholder="Password">
                    <i class="mdi mdi-eye"></i>
                  </div>
                  <div class="mt-5">
                    <input type="submit" class="btn btn-block btn-warning btn-lg font-weight-medium" value="Login">
                  </div>
                  <div class="mt-3 text-center">
                    <a href="register.php" class="auth-link text-white">Register</a>
                  </div>
                </form>                  
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
