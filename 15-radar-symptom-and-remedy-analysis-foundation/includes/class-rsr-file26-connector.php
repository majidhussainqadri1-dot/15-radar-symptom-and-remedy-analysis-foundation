<?php
/**
 * File15 canonical source → File26 PUBLIC governed research trend only.
 * File15's private verified-doctor studies are intentionally NEVER exported.
 * PROPOSED only; review, event/tombstone and staging owner approval pending.
 */
defined('ABSPATH') || exit;
final class RSR_File26_Connector {
    const SLUG='file15-public-research';
    public static function register(){
        add_filter('sabri_file26_connector_manifests',array(__CLASS__,'append'),20);
        add_action('sabri_file26_connectors_ready',array(__CLASS__,'ready'),20);
        if(function_exists('sabri_file26_register_connector')){sabri_file26_register_connector(self::manifest());}
    }
    public static function manifest(){
        return array(
            'slug'=>self::SLUG,'owner_file'=>'File 15','contract_version'=>'1.0',
            'entity_types'=>array('radar_study','research_signal'),
            'privacy_classes'=>array('public'),'visibility_fields'=>array('public'),
            'deletion_semantics'=>'owner_approved_trend_retraction_tombstone_pending',
            'status'=>'proposed','event_contract'=>'RadarTrendReportPublished.v1.pending-file26-cutover',
            'index_schema'=>'sabri.file26.document.v1.1',
            'list_batch'=>array(__CLASS__,'list_batch'),
            'can_view'=>array(__CLASS__,'can_view'),
            'fetch_object'=>array(__CLASS__,'fetch_object'),
            'health'=>array(__CLASS__,'health')
        );
    }
    public static function append($manifests){
        $manifests=is_array($manifests)?$manifests:array();
        foreach($manifests as $row){if(is_array($row)&&($row['slug']??'')===self::SLUG){return $manifests;}}
        $manifests[]=self::manifest();return $manifests;
    }
    public static function ready($registry){
        if(is_object($registry)&&method_exists($registry,'register')){$registry->register(self::manifest());}
    }
    private static function source_ready(){
        return class_exists('RSR_DB')&&class_exists('RSR_Trend_Service')&&
            defined('RSR_SCHEMA_VERSION')&&!(bool)get_option('rsr_safe_mode',false)&&
            array()===RSR_DB::missing_tables();
    }
    private static function project($row,$known_version=0){
        if(!self::source_ready()||!is_array($row)||!in_array((string)($row['status']??''),array('published','corrected'),true)){return null;}
        $version=(int)($row['version']??0);
        if($version<1||($known_version>0&&$known_version!==$version)){return null;}
        $dto=(new RSR_Trend_Service())->get_report((string)$row['public_id'],false);
        if(is_wp_error($dto)||!is_array($dto)||!in_array($dto['status']??'',array('published','corrected'),true)||
            !empty($dto['stale'])||!isset($dto['canonical_url'],$dto['public_id'])){return null;}
        $window=sanitize_text_field((string)($dto['window_type']??''));
        $geo=sanitize_text_field((string)($dto['geography']??''));
        if(!$window||!$geo){return null;}
        $url=esc_url_raw((string)$dto['canonical_url']);
        if(!$url||!wp_http_validate_url($url)){return null;}
        $title='Radar trends: '.$window.' — '.$geo;
        return array(
            'connector_slug'=>self::SLUG,'domain'=>'radar_trends',
            'object_id'=>(string)$dto['public_id'],'object_version'=>$version,'source_event_sequence'=>$version,
            'entity_type'=>'research_signal','locale'=>'en-US',
            'state'=>'published','visibility'=>'public',
            'title'=>$title,'excerpt'=>'Source-governed aggregated research trends; not medical advice.',
            'search_text'=>$title,'canonical_url'=>$url,
            'safety_class'=>'research_not_patient_information'
        );
    }
    public static function list_batch($cursor='',$limit=50,$scope=array()){
        global $wpdb;
        if(!self::source_ready()){return new WP_Error('file15_source_schema_unavailable','Canonical aggregate report schema unavailable.');}
        if(!is_array($scope)||isset($scope['object_id'])){return new WP_Error('file15_partial_scope','Complete owner snapshot only.');}
        if($cursor!==''&&(!is_string($cursor)||!preg_match('/^[0-9]{1,18}$/D',$cursor))){return new WP_Error('file15_invalid_cursor','Invalid report cursor.');}
        $table=RSR_DB::table('reports');$limit=max(1,min(50,(int)$limit));$id=(int)$cursor;$wpdb->last_error='';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,public_id,version,status FROM $table WHERE id>%d ORDER BY id ASC LIMIT %d",$id,$limit+1),ARRAY_A);
        if(!is_array($rows)||$wpdb->last_error){return new WP_Error('file15_reports_unreadable','Cannot read canonical approved radar reports.');}
        $more=count($rows)>$limit;if($more){array_pop($rows);}
        $items=array();
        foreach($rows as $row){$doc=self::project($row,(int)$row['version']);if($doc){$items[]=$doc;}$id=max($id,(int)$row['id']);}
        return array('items'=>$items,'done'=>!$more,'next_cursor'=>$more?(string)$id:'');
    }
    public static function fetch_object($type,$public_id=''){
        global $wpdb;
        if($type!=='research_signal'||!is_string($public_id)||!preg_match('/^[a-f0-9-]{36}$/iD',$public_id)||!self::source_ready()){return null;}
        $table=RSR_DB::table('reports');$wpdb->last_error='';
        $row=$wpdb->get_row($wpdb->prepare("SELECT id,public_id,version,status FROM $table WHERE public_id=%s",$public_id),ARRAY_A);
        if(!is_array($row)||$wpdb->last_error){return null;}
        return self::project($row);
    }
    public static function can_view($document,$audience=array()){
        if(!is_array($document)||($document['connector_slug']??'')!==self::SLUG||
            ($document['visibility']??'')!=='public'||($document['entity_type']??'')!=='research_signal'){return false;}
        $current=self::fetch_object('research_signal',(string)($document['object_id']??''));
        return is_array($current)&&(int)$current['object_version']===(int)($document['object_version']??0);
    }
    public static function health(){
        return self::source_ready()?
            array('state'=>'healthy','owner_file'=>'File 15','private_studies_indexed'=>false,'status'=>'proposed','tombstone_acceptance'=>false):
            array('state'=>'degraded','owner_file'=>'File 15','reason'=>'radar_source_schema_or_safe_mode');
    }
}
