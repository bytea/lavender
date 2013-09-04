<?php
use Lavender\Errno;

return array(
	Errno::UNKNOW => '未知错误',
	Errno::VAR_UNDEFINED => '变量未定义',
	Errno::INDEX_UNDEFINED => '变量索引未定义',
	Errno::CONST_UNDEFINED => '常量未定义',
	Errno::PARAM_INVALID => '参数无效',
	Errno::PARAM_MISSED => '缺少参数',
	Errno::DEFINED_INVALID => '定义错误',
	Errno::INPUT_PARAM_INVALID => '输入的参数无效',
	Errno::INPUT_PARAM_MISSED => '缺少输入参数',

	Errno::AUTH_FAILED => '会话验证失败，请重新登录',

	Errno::FILE_NOTFOUND => '文件未找到',

	Errno::DB_FAILED => '数据库查询失败',
	Errno::DB_CONNECT_FAILED => '数据库连接失败',

	Errno::FILE_FAILED => '文件访问失败',
	Errno::MKDIR_FAILED => '创建目录失败',

	Errno::NETWORK_FAILED => '网络访问失败',

	Errno::CONFIG_TYPE_INVALID => '配置类型无效',
	Errno::CONFIG_ITEM_INVALID => '配置项无效',

	Errno::SESSION_INVALID => '未登录或会话已过期，请登录再使用',
	Errno::SESSION_TIMEOUT => '会话超时，请重新登录',
	Errno::SESSION_ID_INVALID => '会话ID无效',

	Errno::SERIALIZE_FAILED => '数据序列化失败',
	Errno::UNSERIALIZE_FAILED => '数据反序列化失败',

	Errno::PACK_FAILED => '数据打包失败',
	Errno::UNPACK_FAILED => '数据解包失败',

	Errno::ITEM_NOT_FOUND => '项目未找到',

	Errno::IMAGE_TYPE_INVALID => '图片类型无效，可能是类型不支持',
	Errno::IMAGE_SAVE_FAILED => '图片写入文件失败',

	Errno::TOKEN_VERIFY_FAILED => '令牌验证失败',
);
