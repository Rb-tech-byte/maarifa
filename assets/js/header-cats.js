(function(){
  function isMobile(){ return window.innerWidth < 992; }
  function closeAll(){
    document.querySelectorAll('.cat-item.open').forEach(function(el){ el.classList.remove('open'); });
  }
  document.addEventListener('click', function(e){
    if (!e.target.closest('.cat-item')) closeAll();
  });
  document.querySelectorAll('.cat-item > a.cat-link').forEach(function(link){
    function toggleOpen(ev, isTouch){
      var hasChildren = link.getAttribute('data-has-children') === '1';
      if (!hasChildren) return false;
      if (isMobile()) {
        var item = link.closest('.cat-item');
        var isOpen = item.classList.contains('open');
        if (!isOpen) {
          if (ev) ev.preventDefault();
          closeAll();
          item.classList.add('open');
          return true;
        } else if (isTouch) {
          if (ev) ev.preventDefault();
          window.location.href = link.href;
          return true;
        }
      }
      return false;
    }
    link.addEventListener('touchstart', function(ev){ try{ ev.preventDefault(); }catch(_){} toggleOpen(ev, true); }, { passive: false });
    link.addEventListener('click', function(ev){ if (toggleOpen(ev, false)) return; });
  });
  window.addEventListener('resize', function(){ if (!isMobile()) closeAll(); });

  // Drawer toggles
  var catToggle = document.querySelector('.drawer [data-role="toggle"]');
  var catSub = document.querySelector('.drawer [data-role="submenu"]');
  if (catToggle && catSub) {
    catToggle.addEventListener('click', function(){
      var open = catSub.style.display === 'flex';
      catSub.style.display = open ? 'none' : 'flex';
      catSub.style.flexDirection = 'column';
      catToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
    });
  }
  document.querySelectorAll('.drawer [data-role="toggle-parent"]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var sub = btn.nextElementSibling;
      var open = sub && sub.style.display === 'flex';
      if (sub) { sub.style.display = open ? 'none' : 'flex'; sub.style.flexDirection = 'column'; }
      btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    });
  });
})();
