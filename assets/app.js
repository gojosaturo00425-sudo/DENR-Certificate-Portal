document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-confirm]').forEach(el=>{
    el.addEventListener('click',e=>{
      if(!confirm(el.dataset.confirm)){e.preventDefault();}
    });
  });
  const search=document.querySelector('#tableSearch');
  if(search){
    search.addEventListener('input',()=>{
      const q=search.value.toLowerCase();
      document.querySelectorAll('tbody tr').forEach(tr=>{
        tr.style.display=tr.innerText.toLowerCase().includes(q)?'':'none';
      });
    });
  }
});
