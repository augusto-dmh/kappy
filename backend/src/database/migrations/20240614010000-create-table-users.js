"use strict";

module.exports = {
  up: async (queryInterface, Sequelize) => {
    await queryInterface.createTable("users", {
      id: {
        allowNull: false,
        autoIncrement: true,
        primaryKey: true,
        type: Sequelize.INTEGER,
      },
      nickname: {
        type: Sequelize.STRING(20),
        defaultValue: "",
        unique: true,
      },
      checkpoint: {
        type: Sequelize.INTEGER,
        references: {
          model: "lessons",
          key: "id",
        },
        name: "fk_lesson_id",
        defaultValue: 1,
      },
      created_at: {
        type: Sequelize.DATE,
      },
      updated_at: {
        type: Sequelize.DATE,
      },
    });

    await queryInterface.addConstraint("users", {
      fields: ["checkpoint"],
      type: "foreign key",
      name: "fk_checkpoint",
      references: {
        table: "lessons",
        field: "id",
      },
      onDelete: "cascade",
      onUpdate: "cascade",
    });
  },

  down: async (queryInterface, Sequelize) => {
    await queryInterface.removeConstraint("users", "fk_checkpoint");
    await queryInterface.dropTable("users");
  },
};
