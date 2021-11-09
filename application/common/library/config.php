<?php
$config = array (	
		//应用ID,您的APPID。
		'app_id' => "2021002133661479",

		//商户私钥
		'merchant_private_key' => "MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCO126qEcxs0b1/cPoPCfVLzy/RE7/k66Olk1qUKbEyeTpviv0NLUh/EeBr5LKXNgBTL5biowzTgghS00OMG59CBItb/w477XjhEb4kLLOHW6Pa4JZeKHcLpApSdubWGqbg4gS6omNGjFBNGFH6ABFBxt/+hGPQ8LWTDP1uQz5ScFTKn3h0xm0foQk+BxGBbtixTanX9aN945q35PgyZNekJ8RY/vywrubMjPMKV0qBHqhmU/zZkPSlVfOQsTcReG5PGfx2EBjkznl1T2/UJwTLdtt2PB5hrAttucQaCsnxMrygedX5liE/pSA//a/9OGSqflkGXhfkCqPPfCbUWCQzAgMBAAECggEASNRw+Ue/6k/6hasN5HMYXcHSyJCAS/EVbEu4aEjlry1+bPb51SkciKWNvpVJta1z9vYRwZaO6JypL8nF6/79cYStpYdjM4z+EAui3gDovIAuCGBwaqvJHudR2AAF7G6EXa7/6ET5xzOtkdFvor88DUSgWr4XwqWofsSlxv2EHjWb9nWosO2IZnFu4K6ObSP4CWyBVSW7VCjBbnB6fFEegiTorAUusjeqYrLJ5+bLPmSCM6k3GBvjTtm03WCUG6/V15BHP0FG4+r8Qs6uV8LYHnoFlciqsGLuo8iYFkNOVto9QifXxIqL0BJcORg4942w1+1W6N5Md/TCRXXdIC+vEQKBgQD6XAKL5vVEp62GPnTVL+5MGERipFUdCNaJSr6ZFglM3j+uyiK1v2sPZvwquCnSvjvifU7wbFEXqMVtBveDIwIXNg+4uwftJ4R8JNNaXswEuNZR08q8FMDW2uuCNey31cYs5zdWYmUf6lyTl4o7JN0dLUNEXaMVnKQu/OSKOFL3awKBgQCSD0t76AqnMXI52w3yxdud7Ktz8idYCeaDIgJdRBd9HG/dl+LxzstbNJYCr8bzqEHeDKoK7DQvL5iMjSlyIoNaRDm8KToe93YxhQSoz6Y4GsMioXu7Di8Rfq99qkO2oX28rzUbbrEXMYzS9i2tbsWo+T3g2DiLEXailtF67ihgWQKBgHUk4kgl7DOQpb//r1klUUInxK/HJtAsF34sDBzDU9y0zWVyzWTvSR/u1yUCAQfL3WdvrKUQea0xWhdWwC+LDOphcF/Gm8Ha0MHp1T8exiWbeyTUjbMNnuGpk7LcmoO2MkFGev0fkyOo3GJu8M4VxKRnTmdJzQpKvgQCbslB64g7AoGAWJEluJDQROna1fJa1ufbcDvfC4O/D8eRG9s3i86KX7cqrjg3yWEYNsoAXMix33Yb2sXbJpxsWGCIJFJE24zKEaZlTA/DyptL9GMwnByuMj8oLIu3N4o2SGmFiLICNXBfilbD4UqR3/qP5iyZLh2Jhhj8yKbUQp/oTFcf12cq3KECgYEAhsTADJhg1cnXOKSd9cZ0WLzeyhlVwwBFQssjU1ZEUmjAyUiMKBRWaZSsmLNrFHYfsSCbFmz5T44p0qaXqlFjQorck+Jjph5Aeo9rTfEnppsFIHjm6TLthnCmGQyz65Zs5NRozcRh7v8ybUYPJHts8JQKg0OqpLNoHOOWsmDAUDs=",
		
		//异步通知地址
		'notify_url' => "http://yimei.sctqmt.com/addons/epay/api/notifyx/type/alipay",
		
		//同步跳转
		'return_url' => "http://yimei.sctqmt.com/addons/epay/api/returnx/type/alipay",

		//编码格式
		'charset' => "UTF-8",

		//签名方式
		'sign_type'=>"RSA2",

		//支付宝网关
		'gatewayUrl' => "https://openapi.alipay.com/gateway.do",

		//支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
		'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzaVLQCpos3cDpoKjxpCqMUAZmymmWKGo1b59ZIAQt633GZ0Wmi/Q5LWR+F1eACdae4s8cSUt9EgEmkQkeR5KUK+bUa3P91//dEtnXn0pudaVqq8lwi/mAMMln77xiTmvuA1UL/f3Rd+qdj6t3GMSx138joFmdekcBTU46m0TqiFHsYRjNiioVc3OCZY2H0DkfzY9IAzUZ7q6NQ3S13yK1AkJ/YtLAVIoFzgZWZiX0lBWjJA6JOeF83z0ALDBSlYEiAwgzx52W3BD2LOgrGP0kdz/BjYSX5/5kSLDfrZi49LJbZriTTwbIjwva/YdQAkAPIDQ+iacYmUIIbuvhZznpwIDAQAB",
);