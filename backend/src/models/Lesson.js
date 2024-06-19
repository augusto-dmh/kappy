import { Model, DataTypes } from "sequelize";
import Exercise from "./Exercise";

export default class Lesson extends Model {
  static init(sequelize) {
    super.init(
      {
        name: {
          type: DataTypes.STRING,
          allowNull: false,
        },
        previousLesson: {
          type: DataTypes.INTEGER,
          allowNull: true,
          references: {
            model: "lesson",
            key: "id",
          },
        },
      },
      {
        sequelize,
        modelName: "lesson",
      }
    );

    this.addScope("defaultScope", {
      include: [{ model: Exercise, as: "exercises" }],
      attributes: { exclude: ["createdAt", "updatedAt"] },
    });
  }
  static associate(models) {
    this.hasMany(models.user, {
      foreignKey: "checkpoint",
      as: "users",
    });
    this.hasMany(models.exercise, {
      foreignKey: "lessonId",
      as: "exercises",
    });
    this.belongsTo(models.lesson, {
      foreignKey: "previousLesson",
      as: "previous",
    });
  }
}
