@if(config('mybooks.tawk_to_url'))
<!--Start of Tawk.to Script-->
<script type="text/javascript" nonce="{{ app('csp-nonce') }}">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
@auth
Tawk_API.visitor = {
    name: @js(auth()->user()->name),
    email: @js(auth()->user()->email)
};
@endauth
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src=@js(config('mybooks.tawk_to_url'));
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->
@endif
