-- 回滚顺序与依赖关系相反：先删除日志/文件/令牌，再删除用户映射和主表。
-- down 会删除数据，只能在确认已备份或处于开发环境时执行。
DROP TABLE IF EXISTS `{{prefix}}sys_login_log`;
DROP TABLE IF EXISTS `{{prefix}}sys_file`;
DROP TABLE IF EXISTS `{{prefix}}sys_user_token`;
DROP TABLE IF EXISTS `{{prefix}}sys_mini_program_user`;
DROP TABLE IF EXISTS `{{prefix}}sys_user`;
DROP TABLE IF EXISTS `{{prefix}}sys_mini_program`;
