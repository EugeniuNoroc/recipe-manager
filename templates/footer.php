
</main><!-- /.container -->

<footer class="text-center text-muted py-4 mt-4 border-top bg-white">
    <small>Recipe Manager &copy; <?= date('Y') ?></small>
</footer>

<script>
(function () {
  'use strict';
  document.querySelectorAll('form[novalidate]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
</body>
</html>
