class UserlistController < ApplicationController
  before_filter :require_user

  active_scaffold :user
  active_scaffold :user do |config|
    config.columns = [:email, :publisher, :admin, :current_login_at, :current_login_ip, :last_login_at, :last_login_ip ]
    config.create.columns = [:email, :password, :password_confirmation, :admin, :publisher ]
    config.update.columns = [:email, :admin, :publisher ]
    config.columns[:admin].form_ui = :checkbox
  end
end