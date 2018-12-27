<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>LazyStatus</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="/user/node_modules/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="/user/node_modules/simple-line-icons/css/simple-line-icons.css">
  <!-- endinject -->
  <!-- plugin css for this page -->
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="css/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="images/favicon.png" />
</head>

<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.html -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-top justify-content-center">
        <a class="navbar-brand brand-logo" href="/"><img src="images/logo.svg" alt="logo"/></a>
        <a class="navbar-brand brand-logo-mini" href="/"><img src="images/logo-mini.svg" alt="logo"/></a>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-center">
        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item d-none d-lg-block">
            <a class="nav-link" href="#">
              <img class="img-xs rounded-circle" src="/user/avatar/{cookie('user_id')}.png" onerror="this.src='https://www.gravatar.com/avatar/{cookie('user_useremail')}?d=robohash';" alt="">
            </a>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar.html -->
      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">
          <li class="nav-item nav-profile">
            <div class="nav-link">
              <div class="profile-image"> <img src="/user/avatar/{cookie('user_id')}.png" onerror="this.src='https://www.gravatar.com/avatar/{cookie('user_useremail')}?d=robohash';" alt="image"/> <span class="online-status online"></span> </div>
              <div class="profile-name">
                <p class="name">{cookie('username')}</p>
                <div class="badge badge-teal mx-auto mt-3">Online</div>
              </div>
            </div>
          </li>
          <li class="nav-item"><a class="nav-link" href="index.php"><img class="menu-icon" src="images/menu_icons/01.png" alt="menu icon"><span class="menu-title">Dashboard</span></a></li>
          <li class="nav-item"><a class="nav-link" href="add-site.php"><img class="menu-icon" src="images/menu_icons/02.png" alt="menu icon"><span class="menu-title">Add Sites</span></a></li>
          <li class="nav-item"><a class="nav-link" href="profile.php"><img class="menu-icon" src="images/menu_icons/03.png" alt="menu icon"><span class="menu-title">Update Profile</span></a></li>
          <li class="nav-item"><a class="nav-link" href="inbox.php"><img class="menu-icon" src="images/menu_icons/04.png" alt="menu icon"><span class="menu-title">Inbox</span></a></li>
          <li class="nav-item"><a class="nav-link" href="api-docs.php"><img class="menu-icon" src="images/menu_icons/06.png" alt="menu icon"><span class="menu-title">API</span></a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php"><img class="menu-icon" src="images/menu_icons/05.png" alt="menu icon"><span class="menu-title">Logout</span></a></li>
        </ul>
      </nav>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          {block name="content"}{/block}
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <footer class="footer">
          <!-- <div class="container-fluid clearfix">
            <span class="text-muted d-block text-center text-sm-left d-sm-inline-block">Copyright © 2018 <a href="http://www.bootstrapdash.com/" target="_blank">Bootstrapdash</a>. All rights reserved.</span>
            <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Hand-crafted & made with <i class="mdi mdi-heart text-danger"></i></span>
          </div> -->
        </footer>
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- plugins:js -->
  <script src="/user/node_modules/jquery/dist/jquery.min.js"></script>
  <script src="/user/node_modules/popper.js/dist/umd/popper.min.js"></script>
  <script src="/user/node_modules/bootstrap/dist/js/bootstrap.min.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page-->
  <script src="/user/node_modules/chart.js/dist/Chart.min.js"></script>
  <!-- End plugin js for this page-->
  <!-- inject:js -->
  <script src="js/off-canvas.js"></script>
  <script src="js/misc.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="js/dashboard.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB5NXz9eVnyJOA81wimI8WYE08kW_JMe8g&callback=initMap" async defer></script>
  <script src="js/maps.js"></script>
  <!-- End custom js for this page-->
</body>

</html>