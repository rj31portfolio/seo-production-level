<?php
namespace App\Services\Seo;
class KeywordEngine
{
    public function analyze(string $tool,array $input): array
    {
        $keywords=array_values(array_unique(array_filter(array_map(fn (string $line)=>trim(preg_replace('/\s+/u',' ',$line)),preg_split('/\R/u',$input['keywords'] ?? '')))));
        if(!$keywords || count($keywords)>500) { throw \Illuminate\Validation\ValidationException::withMessages(['keywords'=>'Supply between 1 and 500 keywords, one per line.']); }
        foreach($keywords as $keyword) { if(mb_strlen($keyword)>200) { throw \Illuminate\Validation\ValidationException::withMessages(['keywords'=>'Each keyword must be at most 200 characters.']); } }
        if(in_array($tool,['question-keywords','longtail-keywords','local-keywords'],true)) {
            $seeds=$keywords;$keywords=[];
            foreach($seeds as $seed) {
                $patterns=match($tool) {
                    'question-keywords'=>['What is %s?','How does %s work?','How much does %s cost?','How to choose %s?','When do you need %s?'],
                    'longtail-keywords'=>['%s for beginners','best %s for small businesses','affordable %s services','%s pricing comparison','how to get started with %s'],
                    'local-keywords'=>['%s in '.$input['location'],'best %s in '.$input['location'],'%s near '.$input['location'],'local %s services in '.$input['location']],
                };
                foreach($patterns as $pattern) { $keywords[]=sprintf($pattern,$seed); }
            }
            $keywords=array_slice(array_values(array_unique($keywords)),0,500);
        }
        $rows=[];$groups=[];
        foreach($keywords as $keyword) {
            $intent=$this->intent($keyword);$terms=$this->topicTerms($keyword);$topic=$terms[0] ?? mb_strtolower($keyword);
            $row=['keyword'=>$keyword,'intent'=>$intent,'cluster'=>$topic.' / '.$intent,'suggested_path'=>'/'.trim(preg_replace('/[^\p{L}\p{N}]+/u','-',mb_strtolower($keyword)),'-'),'search_volume'=>null,'cpc'=>null,'confidence'=>'Rule-based heuristic; review required'];
            $rows[]=$row;$groups[$row['cluster']][]=$keyword;
        }
        return ['metrics'=>['keywords'=>count($rows),'clusters'=>count($groups)],'keywords'=>$rows,'clusters'=>$groups,'notes'=>['Source: rule-based internal analysis of supplied keywords. Generated phrases are editorial suggestions, not observed search queries.','Intent rules are English-language heuristics. Clustering groups by the first meaningful term and inferred intent; this is not SERP-based clustering.','Search volume, CPC, keyword difficulty and traffic are not available from this engine.']];
    }
    public function intent(string $keyword): string
    {
        $keyword=mb_strtolower($keyword);
        foreach(['local'=>'/\b(near me|nearby|in|local)\b/u','transactional'=>'/\b(buy|order|hire|book|subscribe|coupon|discount)\b/u','commercial'=>'/\b(best|review|reviews|compare|comparison|pricing|price|cost|vs)\b/u','informational'=>'/\b(what|how|why|when|where|guide|tutorial|learn)\b/u','navigational'=>'/\b(login|sign in|official|website)\b/u'] as $intent=>$pattern) { if(preg_match($pattern,$keyword)) { return $intent; } }
        return 'unclassified';
    }
    private function topicTerms(string $keyword): array
    {
        $terms=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($keyword),-1,PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($terms,fn (string $term)=>!in_array($term,['a','an','the','for','to','in','of','and','or','is','how','what','why','when','where','best','buy','hire','near','me','local','review','reviews'],true)));
    }
}
