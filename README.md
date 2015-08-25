## migrate

drush civicrm-api contribution.migrate option.limit=10000 debug=0

## cleanup

To delete duplicate mandates (based on the same source id, the pledge id in our case)
delete m1 from civicrm_sdd_mandate m1, civicrm_sdd_mandate m2 where m1.id<m2.id and m1.source=m2.source;

delete r from civicrm_contribution_recur as r left join civicrm_sdd_mandate as m on m.entity_table="civicrm_contribution_recur" and m.entity_id=r.id where m.id is null;


select * from civicrm_contribution_recur as r left join civicrm_sdd_mandate as m on m.entity_table="civicrm_contribution_recur" and m.entity_id=r.id where m.id is null limit 1;
