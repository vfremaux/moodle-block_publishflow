function doStuff(hosts, wwwroot){

	if(hosts == 0){ //required to autopopulate the first list.
		var platformid = hosts;
	} else { 
		var platformid = hosts.options[hosts.selectedIndex].value;
	}
	
	var params = "platformid="+platformid;
	var url = wwwroot+'/blocks/publishflow/ajax/categorybuilder.php?'+params;	

	var responseSuccess = function(o){
		var response = o.responseText;
		var list = document.getElementById('category');	
		var jsonBuff = o.responseText.substring(o.responseText.indexOf('{'));
	
		if (jsonBuff == ''){
		    var response = new Object();
		    response.success == false;
		    response.data = new Object();
		    response.data.text = 'Server response error'; 
		} else {
		    var catArray = JSON.parse(response,null);
		}
		
		var html = '<select id="publishflow-category-select" name="category" size="1">';
		for (i = 0; i < catArray.length; i++) {
		    html = html+'<option value='+catArray[i].orid+'>'+catArray[i].name+'</option>';
		}
		html = html+'</select>';
		list.innerHTML = html;
	};
	
	var responseFailure = function(o){
	alert('FAIL');
	};
	
	var AjaxObject = {	
	  processResult:function(o){},
	
	  startRequest:function(){    	    
	      YAHOO.util.Connect.asyncRequest('GET', url, callback, params);
	  }
	};
	
	var callback = {
		success:responseSuccess,
		failure:responseFailure,
		scope: AjaxObject
	};
		
	AjaxObject.startRequest();
}