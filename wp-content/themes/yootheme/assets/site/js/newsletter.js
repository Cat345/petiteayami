/*! YOOtheme Pro v5.0.19 | https://yootheme.com */

const{on:c,$:d,addClass:r,removeClass:l}=window.UIkit.util;c(document,"submit",".js-form-newsletter",async o=>{o.preventDefault();const t=o.target,s=d(".message",t);r(s,"uk-hidden");try{const e=await fetch(t.action,{method:"post",body:new FormData(t)});if(e.ok){const{message:n,redirect:i}=await e.json();n?(a(n),t.reset()):i?window.location.href=i:a("Invalid response.",!0)}else a(await e.text(),!0)}catch{}function a(e,n){l(s,"uk-hidden uk-text-danger"),r(s,`uk-text-${n?"danger":"success"}`),s.innerText=e}});
