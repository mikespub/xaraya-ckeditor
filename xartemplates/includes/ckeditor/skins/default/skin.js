﻿/*
Copyright (c) 2003-2009, CKSource - Frederico Knabben. All rights reserved.
For licensing, see LICENSE.html or http://ckeditor.com/license
*/

CKEDITOR.skins.add('default',(function(){var a=[],b=[];if(CKEDITOR.env.ie&&CKEDITOR.env.version<=6)a.push('icons.gif','images/sprites.gif','images/dialog.sides.gif');return{preload:a,editor:{css:['editor.css']},dialog:{css:['dialog.css'],js:b}};})());(function(){var a='default',b=function(c,d,e,f){var g=d?c.parts[d]:c._.element.getFirst();if(e)g.setStyle('width',e+'px');if(f)g.setStyle('height',f+'px');};if(CKEDITOR.dialog){CKEDITOR.dialog.setMargins(0,14,18,14);CKEDITOR.dialog.on('resize',function(c){var d=c.data,e=d.width,f=d.height,g=d.dialog,h=CKEDITOR.document.$.compatMode=='CSS1Compat';if(d.skin!=a)return;b(g,'t',e-32,16);b(g,'t_resize',e-32,null);b(g,'l',16,f-67);b(g,'l_resize',null,f-22);b(g,'c',e-32,f-67);b(g,'r',16,f-67);b(g,'r_resize',null,f-22);b(g,'b',e-60,51);b(g,'b_resize',e-32,null);b(g,'tabs_table',e-32,null);if(CKEDITOR.env.ie){var i=e-34,j=g.getPageCount()>1?f-106:f-84,k=g.parts.contents.getChildCount();if(!h){i+=2;j+=2;g.parts.tabs.setStyle('top','33px');}b(g,'title',h?e-52:e-32,h?null:31);b(g,'contents',i,j);b(g,'footer',e-32);for(var l=0;l<k;l++){var m=g.parts.contents.getChild(l);if(m instanceof CKEDITOR.dom.element&&(m.$.className||'').search('cke_dialog_page_contents')>-1)m.setStyles({width:i-(h?20:0)+'px',height:j-(h?20:0)+'px'});}}b(g,null,e,f);});}})();
