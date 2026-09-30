CREATE OR REPLACE VIEW service_orders AS
SELECT o.id,o.user_id,o.product_name,o.qty,o.unit_price,o.total_amount,o.provider,o.provider_ref,o.status,o.created_at,u.email AS customer_email,u.name AS customer_name
FROM orders o LEFT JOIN users u ON u.id=o.user_id;
