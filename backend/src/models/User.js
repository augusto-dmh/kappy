import { Model, DataTypes } from "sequelize";

export default class User extends Model {
  static init(sequelize) {
    super.init(
      {
        nickname: {
          type: DataTypes.STRING(20),
          defaultValue: "",
          unique: true,
        },
        checkpoint: {
          type: DataTypes.INTEGER,
          references: {
            model: "lessons",
            key: "id",
          },
          defaultValue: 1,
        },
      },
      {
        sequelize,
        modelName: "user",
      }
    );
  }

  static associate(models) {
    this.belongsTo(models.lesson, {
      foreignKey: "checkpoint",
      as: "lesson",
    });
  }
}
