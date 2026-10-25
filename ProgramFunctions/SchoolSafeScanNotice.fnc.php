<?php
// Pure content adapter for native Notify/Studio/Mail/WhatsApp. Not a ledger,
// invoice, payment receipt, recipient resolver or transport.
function SchoolSafeScanFinancialNotice(array $observation): string
{
    $s=$observation;$school=$s['school']??[];$student=$s['student']??[];
    $ledger=$s['ledger']??[];$policy=$s['policy']??null;$financial=$s['financial']??[];
    $known=function($value){return is_string($value)&&trim($value)!==''?$value:'non renseigné';};
    $currency=is_string($ledger['currency']??null)&&($ledger['currency_state']??null)==='known'?$ledger['currency']:null;
    $money=function($key)use($ledger,$currency){
        $value=$ledger[$key]??null;
        return is_string($value)?$value.($currency===null?' (devise non renseignée)':(' '.$currency)):'non disponible';
    };
    $state=$financial['state']??null;
    $status=$state==='inactive'?'Aucun barème actif — aucune validation financière d’entrée':
        ($state==='unavailable'?'Contrôle indisponible — aucune présomption d’impayé':
        (['blue'=>'Bleu — en ordre pour ce contrôle','yellow'=>'Jaune — tolérance du modèle','red'=>'Rouge — entrée refusée pour ce contrôle'][$financial['color']??'']??'État non disponible'));
    $reasons=['threshold_met'=>'Minimum effectif atteint','net_obligation_zero'=>'Aucune obligation nette positive pour ce contrôle',
        'within_tolerance'=>'Versement net dans la tolérance configurée','below_tolerance'=>'Minimum/tolérance non satisfaits ; un versement net positif est requis pour le jaune',
        'no_data'=>'Données financières indisponibles au moment du contrôle','no_active_policy'=>'Aucun barème de contrôle actif'];
    $reason=$financial['reason']??null;
    $lines=['Contrôle financier — état observé, pas un reçu de paiement.',
        'École : '.$known($school['name']??null).' (identifiant '.(int)($school['id']??0).')',
        'Élève : '.$known($student['name']??null).' (identifiant '.(int)($student['id']??0).')',
        'Classe : '.$known($student['class_name']??null),
        'Année scolaire : '.(int)($s['syear']??0),
        'Périmètre : montants cumulés de l’année scolaire sélectionnée.',
        'Date/heure du contrôle : '.$known($s['recorded_at']??null),
        'Nature : '.(($s['operation']??null)==='entry'?'contrôle lié à une demande d’entrée':'consultation — ne vaut pas autorisation d’entrée'),
        'Modèle : '.(is_array($policy)?$known($policy['label']??null):'aucun actif'),
        'Résultat observé : '.$status,
        'Motif : '.($reasons[$reason]??$known($reason)),
        'Frais positifs : '.$money('billed_positive'),
        'Exonérations/annulations liées (signées) : '.$money('waiver_adjustments'),
        'Autres ajustements de frais (signés) : '.$money('other_fee_adjustments'),
        'Total net facturé : '.$money('net_billed'),
        'Versements positifs : '.$money('paid_positive'),
        'Remboursements/annulations liés (signés) : '.$money('refund_adjustments'),
        'Autres ajustements de versements (signés) : '.$money('other_payment_adjustments'),
        'Payé net effectif : '.$money('paid_net'),
        'Solde observé : '.$money('balance'),
        'Restant dû observé : '.$money('amount_due'),
        'Crédit constaté : '.$money('credit'),
        'Devise configurée au moment du contrôle : '.($currency??'non renseignée ; aucune conversion effectuée')];
    if(is_array($policy)){
        foreach(['minimum'=>'Minimum configuré','effective_minimum'=>'Minimum effectif plafonné au net facturé','tolerance_floor'=>'Plancher après tolérance']as$key=>$label){
            $value=$policy[$key]??null;
            $lines[]=$label.' : '.(is_string($value)?$value.($currency===null?' (devise non renseignée)':(' '.$currency)):'non disponible');
        }
        $lines[]='Tolérance configurée : '.(is_string($policy['tolerance_percent']??null)||is_int($policy['tolerance_percent']??null)||is_float($policy['tolerance_percent']??null)?$policy['tolerance_percent'].' %':'non disponible');
    }
    $lines[]='Échéances nominales : '.$known($ledger['nominal_due_from']??null).' → '.$known($ledger['nominal_due_to']??null);
    $lines[]='Les paiements ne sont pas affectés par frais : ces dates ne prouvent pas un montant impayé à une échéance donnée.';
    $lines[]='Un règlement ou ajustement ultérieur peut modifier le dossier. Vérifier la situation actuelle auprès du bureau de l’école.';
    $lines[]='Action : '.$known($s['action']??null);
    return implode("\n",$lines);
}
