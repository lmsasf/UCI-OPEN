<?php
class Admin_TestimonialController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    	$this->_helper->layout()->setLayout('admin');
    }
	/**
	 * testimonials list
	 */
    public function indexAction()
    {
    	$this->view->headTitle('Testimonials :: List');
        // action body
    }
    
    public function testimonialgridAction(){
    	$this->_helper->layout()->setLayout('empty');
    	// don't need a view to render
    	$this->_helper->viewRenderer->setNoRender();
    	$filters = Zend_Json_Decoder::decode($this->getRequest()->getParam('filters', '{}'));
    	$where = '';
    	if(!is_null($filters)){
    		$where = $this->buildWherelp($filters) . $where;
    	}
    	 
    	$i = 0;
    
    	$sEcho = $this->getRequest()->getParam('sEcho', 4);
    	// sorting
    	$iSortCol_0 = $this->getRequest()->getParam('iSortCol_0', 1);
    	$sSortDir_0 = $this->getRequest()->getParam('sSortDir_0', 'asc');
    	$sort = array('columna'=> $iSortCol_0 , 'direccion'=> $sSortDir_0);
    	//Pagging
    	$iDisplayLength=$this->getRequest()->getParam('iDisplayLength', 50); // limit
    	$iDisplayStart = $this->getRequest()->getParam('iDisplayStart', 0); // offset
    	$limit = array('limit'=>$iDisplayLength, 'offset'=> $iDisplayStart);
    	//search
    	$sSearch = $this->getRequest()->getParam('sSearch', '');
    
    
    	$Testimonial= new Table_Testimonials();
    	$testimonials = $Testimonial->getTestimonialGrid($where, $sort, $limit, $sSearch );
    
    	$cursor = $testimonials['cursor'];
    	$totalRegistros = $testimonials['count'];
    	$totalRegistrosWere = $testimonials['countWhere'];
    
    	echo '{"aaData": [';
    	while ($row = $cursor->fetch(Zend_Db::FETCH_NUM)) {
    
    		if ($i <= 5000) {
    			if ($i > 0) {
    				echo ",";
    			}
    			$json = '{"DT_RowId": "'.$row[0].'", ';
    			$PK = array_shift($row);
    			foreach($row AS $k => $v){
    				$json.= '"'.$k.'":'.Zend_Json_Encoder::encode($v).',';
    			}
    			$json = substr ($json, 0, -1);
    			$json .= '}';
    			echo $json;
    			$i++;
    		} else {
    			break;
    		}
    	}
    	echo "],";
    	echo '"sEcho": '.$sEcho.',';
    	echo '"iTotalRecords":'. '"'.$totalRegistros.'",';
    	echo '"iTotalDisplayRecords": "'.$totalRegistrosWere.'"';
    	echo "}";
    }
    
    /**
     * Ajax to delete testimonials
     */
    public function deleteAction(){
    	$this->_helper->layout()->setLayout('empty');
    	$this->_helper->viewRenderer->setNoRender();
    	$Testimonial = new Table_Testimonials();
    		
    	try {
    		$Id = $this->getRequest()->getParam('Id', null);
    		if( is_null($Id) ){
    			throw new Exception( 'Insufficient parameters' );
    		}
    		$resp = $Testimonial->delete("idTes = $Id");
    		$this->_forward('index', 'testimonial', 'admin');
    	} catch (Exception $e) {
    		throw new Exception( $e->getMessage() );
    	}
    }

    public function savevisibleAction(){
    	$this->_helper->layout()->setLayout('empty');
    	$this->_helper->viewRenderer->setNoRender();
    	$Testimonial = new Table_Testimonials();
    
    	try {
    		$Id = $this->getRequest()->getParam('Id', null);
    		if( is_null($Id) ){
    			throw new Exception( 'Insufficient parameters' );
    		}
    		$row = $Testimonial->fetchRow( 'idTes='.$Id );
    		$row->tesVisible = $this->getRequest()->getParam('value', 0);
    		$Id = $row->save();
    		if($Id){
    			echo Zend_Json_Encoder::encode(array('Id'=> $Id));
    		} else {
    			throw new Exception('Failed to update the state, this is an error associated with the database, please refresh the page and try again');
    		}
    	} catch (Exception $e) {
    		echo $e->getMessage();
    	}
    }
    
    
    /**
     * Where construction method to filter the grid testimonials
     */
    private function buildWherelp($filters){
    	$sql = '';
    	foreach ($filters AS $campo => $opciones ) {
    		$valores = $opciones['values'];
    		$operador = $opciones['op'];
    		switch ($operador) {
    			case 'EQ':
    				$operador = '= ALL';
    				break;
    			case 'NE':
    				$operador = '!= ALL';
    				break;
    			case 'GT':
    				$operador = '> ANY';
    				break;
    			case 'LT':
    				$operador = '< ANY';
    				break;
    			case 'GE':
    				$operador = '>=';
    				break;
    			case 'LE':
    				$operador = '<=';
    				break;
    			case 'IN':
    				$operador = 'IN';
    				break;
    			case 'BETWEEN':
    				$operador = 'BETWEEN';
    				break;
    			case 'LIKE':
    				$operador = 'LIKE';
    				break;
    			default:
    				throw new Exception('Operador de filtro avanzado no soportado');
    				break;
    		}
    			
    
    		if(is_array($valores)){
    			if($operador === 'BETWEEN'){
    				$sql .= ' AND DATE_FORMAT('  . $campo . ",'%Y-%m-%d') " . $operador . ' ';
    			}elseif($operador === 'LIKE'){
    				$sql .= ' AND LOWER('  . $campo . ') ' . $operador . " '%";
    			}else{
    				$sql .= ' AND '  . $campo . ' ' . $operador . ' (';
    			}
    			$removeChars = $operador === 'BETWEEN' ?  5 : 1 ;
    			foreach ($valores AS $valor ){
    				$patron= "/[0-9]{4}-[0-9]{2}-[0-9]{2}$/";
    				$valor = preg_match($patron, $valor) ? "'". $valor ."'" : ( is_numeric($valor) ? $valor : ( $operador !=='LIKE' ? "'" . $valor . "'": strtolower($valor) ) );
    				$valor = $operador === 'LIKE' ? str_replace( ' ', '%', $valor ) : $valor;
    				$separador = $operador === 'BETWEEN'? ' AND ' : ($operador==='LIKE' ? '%': ',');
    				$sql .= $valor . $separador;
    			}
    			$sql = substr ($sql, 0, ( $removeChars * -1 ) );
    			if($operador === 'BETWEEN'){
    				$sql .= ' ';
    			}elseif($operador === 'LIKE'){
    				$sql .= "%' ";
    			} else {
    				$sql .= ') ';
    			}
    		}else{
    			if($operador === 'BETWEEN'){
    				$sql .= ' AND DATE_FORMAT('  . $campo . ",'%Y-%m-%d') " . $operador . ' ';
    			}elseif($operador === 'LIKE'){
    				$sql .= ' AND LOWER('  . $campo . ') ' . $operador . " '%";
    			}else{
    				$sql .= ' AND '  . $campo . ' ' . $operador ;
    			}
    			$valor = $valores;
    			$patron= "/[0-9]{4}-[0-9]{2}-[0-9]{2}$/";
    			$valor = preg_match($patron, $valor) ? "'". $valor ."'" : ( is_numeric($valor) ? $valor : ( $operador !=='LIKE' ? "'" . $valor . "'": strtolower($valor) ) );
    			$separador = $operador;
    			$sql .= $valor ;
    		}
    	}
    
    	return $sql;
    }
}    