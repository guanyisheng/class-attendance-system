以下是为课堂考勤系统编写的自述文件（`README.md`），包含系统简介、功能说明、使用指南、技术栈和更新日志等内容：


# 课堂考勤系统  
**制作人**：云南经贸外事职业学院 24级计算机应用技术1班 管乙聲  
**更新时间**：2025年04月24日  


以下是一份关于考勤系统从最初版本到当前版本的更新日志示例，你可以根据实际情况进行调整和修改。

### 考勤系统更新日志

#### 版本 1.0 - 初始版本
**主要功能**：
1. **学生名单导入**：支持手动输入学生姓名，每行一个，将学生信息导入系统。
2. **考勤记录**：对导入的学生进行考勤登记，提供“正常”“病假”“公假”“迟到”“缺勤”五种考勤状态选择。
3. **考勤确认**：确认当天的考勤信息，并将记录保存到数据库。
4. **异常考勤查看**：查看当天非“正常”状态的学生考勤记录。
5. **日期查询**：根据日期查询特定日期的考勤时间点和考勤记录。
6. **人员搜索**：通过学生姓名搜索其考勤记录。
7. **考勤记录导出**：支持导出全部考勤记录和异常考勤记录为 CSV 文件。

#### 版本 1.1 - 新增事假状态
**主要更新内容**：
1. **考勤状态扩展**：新增“事假”考勤状态，完善考勤状态类型。
2. **界面更新**：在考勤登记界面添加“事假”状态选项，并为其设置相应的视觉样式。
3. **数据库更新**：如果 `attendances` 表的 `status` 字段使用 `ENUM` 类型，更新该字段定义以包含“事假”状态。

#### 版本 1.2 - 用户登录与学生名单预设功能
**主要更新内容**：
1. **用户登录系统**：
    - 引入用户会话管理，使用 `session_start()` 管理用户登录状态。
    - 提供登录界面，用户输入用户名和密码进行验证，验证通过后进入考勤系统。
    - 预设管理员账号（用户名：`admin`，密码：`admin123`），方便系统管理。
2. **学生名单预设功能**：
    - 允许用户保存预设的学生名单，包括预设名称和学生列表。
    - 用户可以选择已保存的预设名单，快速加载学生信息进行考勤登记。
    - 新增 `presets` 表用于存储用户的预设名单信息。
3. **用户注册功能**：
    - 提供注册界面，用户可以输入新用户名和密码进行注册。
    - 系统会检查用户名是否已存在，避免重复注册。

### 注意事项
- 从版本 1.2 开始，用户需要先登录才能访问考勤系统的各项功能。
- 为了保障系统安全，建议在后续版本中对用户密码进行加密存储。 


## 六、联系方式  
- **作者**：管乙聲  
- **邮箱**：2141516768@qq.com  
- **反馈**：如有问题或建议，可在系统底部“制作人信息”处提交反馈表单。  


**感谢使用！** 🌟
