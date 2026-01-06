(function(){
  function debounce(fn, delay){ var t; return function(){ clearTimeout(t); var a=arguments, ctx=this; t=setTimeout(function(){ fn.apply(ctx,a); }, delay); }; }
  var base = (window.APP_BASE && typeof window.APP_BASE === 'string' && window.APP_BASE) || (location.origin + (location.pathname.indexOf('/ak23downloadapp') !== -1 ? '/ak23downloadapp' : ''));
  var input = document.getElementById('course-search-input');
  var clearBtn = document.getElementById('search-clear-btn');
  var suggestionsBox = document.getElementById('search-suggestions');
  var spinner = document.getElementById('search-loading-spinner');
  if (!input || !clearBtn || !suggestionsBox) return;
  var suggestions = [], selectedIndex = -1;

  input.addEventListener('input', function(){ clearBtn.style.display = this.value ? 'block' : 'none'; });
  clearBtn.addEventListener('click', function(){ input.value=''; suggestionsBox.style.display='none'; clearBtn.style.display='none'; selectedIndex=-1; });

  var fetchSuggestions = debounce(function(){
    var query = (input.value || '').trim();
    if (!query){ suggestionsBox.style.display='none'; if (spinner) spinner.style.display='none'; return; }
    if (spinner) spinner.style.display='inline-block';
    fetch(base + '/api/search.php?q=' + encodeURIComponent(query) + '&suggestions=true')
      .then(function(res){ return res.json(); })
      .then(function(data){ if (spinner) spinner.style.display='none'; suggestions = Array.isArray(data.suggestions)?data.suggestions:[]; renderSuggestions(); })
      .catch(function(){ if (spinner) spinner.style.display='none'; suggestionsBox.style.display='none'; });
  }, 300);
  input.addEventListener('input', fetchSuggestions);

  function escapeHtml(text){
    return String(text).replace(/[&<>"'`=\/]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','`':'&#96;','=':'&#61;','/':'&#47;'})[s]; });
  }

  function renderSuggestions(){
    if (!suggestions.length){ suggestionsBox.innerHTML = '<div style="padding:1rem;text-align:center;color:#FF6600;">No matches found.</div>'; suggestionsBox.style.display='block'; return; }
    suggestionsBox.innerHTML = suggestions.map(function(item, idx){
      var img = item.thumbnail || '';
      if (img && !(img.startsWith('http') || img.startsWith('/'))) {
        img = base + '/' + img.replace(/^\/+/, '');
      }
      var url = base + '/course-details.php?slug=' + encodeURIComponent(item.slug);
      var thumbHtml = img
        ? '<img src="'+img+'" alt="'+escapeHtml(item.name)+'" style="width:50px;height:50px;object-fit:cover;border-radius:0.5rem;box-shadow:0 1px 6px #FF660022;">'
        : '<div aria-hidden="true" style="width:50px;height:50px;border-radius:0.5rem;background:linear-gradient(135deg,#f1f1f1,#e5e5e5);border:1px solid #ddd;box-shadow:0 1px 6px #FF660022;"></div>';
      return '<div class="suggestion-item" tabindex="0" data-url="'+url+'"\
 style="display:flex;gap:1rem;align-items:center;padding:0.8rem 1rem;border-bottom:1px solid #eee;cursor:pointer;">\
 '+thumbHtml+'\
 <div>\
       <div style="font-weight:bold;color:#000;">'+escapeHtml(item.name)+'</div>\
       <div style="font-size:0.9rem;color:#FFD700;">'+escapeHtml(item.category_name)+'</div>\
       <div style="font-size:0.88rem;color:#fff;">'+(item.price && item.price>0 ? 'TSH ' + Number(item.price).toLocaleString() : 'FREE')+'</div>\
     </div>\
</div>';
    }).join('');
    suggestionsBox.style.display='block';
    selectedIndex=-1;
  }

  input.addEventListener('keydown', function(e){
    if (!suggestions.length || suggestionsBox.style.display !== 'block') return;
    var items = suggestionsBox.querySelectorAll('.suggestion-item');
    if (e.key === 'ArrowDown'){ e.preventDefault(); selectedIndex = (selectedIndex + 1) % items.length; highlight(items); }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); selectedIndex = (selectedIndex - 1 + items.length) % items.length; highlight(items); }
    else if (e.key === 'Enter'){
      if (selectedIndex >= 0 && items[selectedIndex]) { window.location.href = items[selectedIndex].getAttribute('data-url'); }
      else { var f = document.getElementById('course-search-form'); if (f) f.submit(); }
    } else if (e.key === 'Escape'){ suggestionsBox.style.display='none'; }
  });

  function highlight(items){
    items.forEach(function(item, idx){ item.style.background = idx === selectedIndex ? '#FF660022' : '#fff'; if (idx === selectedIndex) item.focus(); });
  }

  document.addEventListener('mousedown', function(e){ if (!suggestionsBox.contains(e.target) && e.target !== input) { suggestionsBox.style.display='none'; } });
  suggestionsBox.addEventListener('mousedown', function(e){ var item = e.target.closest('.suggestion-item'); if (item && item.getAttribute('data-url')) { window.location.href = item.getAttribute('data-url'); } });
})();
