</main>

<script>
  const drawer = document.getElementById("drawer");
  const overlay = document.getElementById("drawerOverlay");
  const btn = document.getElementById("menuBtn");
  const closeBtn = document.getElementById("drawerClose");

  function openDrawer(){
    drawer?.classList.add("open");
    overlay?.classList.add("open");
    drawer?.setAttribute("aria-hidden","false");
  }
  function closeDrawer(){
    drawer?.classList.remove("open");
    overlay?.classList.remove("open");
    drawer?.setAttribute("aria-hidden","true");
  }

  btn?.addEventListener("click", () => {
    if (drawer?.classList.contains("open")) closeDrawer();
    else openDrawer();
  });

  closeBtn?.addEventListener("click", closeDrawer);
  overlay?.addEventListener("click", closeDrawer);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeDrawer();
  });
</script>

</body>
</html>
