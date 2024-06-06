import { Model, DataTypes } from "sequelize";

export default class UserAvatar extends Model {
  static init(sequelize) {
    super.init(
      {
        userId: {
          type: DataTypes.INTEGER,
          allowNull: false,
          references: {
            model: "users",
            key: "id",
          },
        },
        avatarId: {
          type: DataTypes.INTEGER,
          allowNull: false,
        },
      },
      {
        sequelize,
        modelName: "userAvatar",
      }
    );
  }

  static associate(models) {
    this.belongsTo(models.user, {
      foreignKey: "userId",
      as: "user",
    });
    this.belongsTo(models.avatar, {
      foreignKey: "avatarId",
      as: "avatar"
  })
}}
