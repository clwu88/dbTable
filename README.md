#dbTable  
##使用MySQL表结构的维护配置化，可以被版本工具管理。


Usage: php /mnt/vm2/project/dbTable/tools/dbTable.helper.php  
            [ -C 关闭颜色显示 ] [ -n 关闭在 CREATE TABEL 语句中比较异同 ]   
            [ -d[tableName, ...] 把DB上的表 转存成    本地的表配置文件，【注意】-d和tableName之间不能有空格，tableName与tableName之间用,分隔 ] [ -y 总是同意 ]  
            [ -h --help ]

Q: 不知道表的配置文件怎么写？  
A: 可以用 -d 选项把 DB 的表 转存成本地的表配置文件，  
   打开这个本地的表配置文件 学习 表配置文件 应该怎么写

Q: 本地的表配置文件 存放在哪？  
A: tools/dbTable/ 目录，文件名为 {$tableName}.table.php


![使用示例](http://git.oschina.net/clwu/dbTable/attach_files/download?i=62732&u=http%3A%2F%2Ffiles.git.oschina.net%2Fgroup1%2FM00%2F00%2F78%2FZxV3cFeFhbyAebjoAAJScfgCmYs023.png%3Ftoken%3D8388a1382ea199c5d36df88fabecfcd1%26ts%3D1468368408%26attname%3Dscreenshot1.png "使用示例")


