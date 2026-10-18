begin;
do $$ begin
 if current_database()<>'schoolsafe_cards_isolated' then raise exception 'ISOLATED_ONLY'; end if;
end $$;
grant execute on function api.scan_control_grant_list(),api.scan_control_grant_set(uuid,text,text,text),
 api.pickup_external_register(uuid,jsonb),api.pickup_photo_review(uuid,text,text),
 api.scan_control_portrait_set(uuid,text),api.pickup_authorization_validate(uuid),
 api.card_print_request_create(uuid,text,text,text,text,text,jsonb) to schoolsafe_card_bridge;
commit;
