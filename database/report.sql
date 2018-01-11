-- Profit and losses
select p.created_at,p.size, p.amount, o.amount, o.side, IF(p.amount >o.amount , 'LOSS','PROFIT' ) lp,
o.amount * o.size - p.amount*p.size diff
from orders o , positions p where o.`position_id` = p.id AND o.status = 'done' AND o.side='sell' order by p.created_at desc; 
